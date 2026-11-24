<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class Csv
{
    /** Stream rows as a CSV download. Cells starting with = + - @ are prefixed to stop formula injection. */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function safe($v): string
    {
        $v = (string) $v;

        return ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($v)) ? "'".$v : $v;
    }

    /** Read an uploaded CSV into an array of associative rows keyed by normalised header. */
    public static function read(string $path, int $max = 5000): array
    {
        $fh = fopen($path, 'r');
        $first = fgets($fh);
        rewind($fh);
        $delim = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        $header = null;
        $rows = [];
        while (($data = fgetcsv($fh, 0, $delim)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $data);
                $header = array_map(fn ($h) => preg_replace('/[^a-z0-9]+/', '_', $h), $header);
                continue;
            }
            if (count(array_filter($data, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }
            $rows[] = $row;
            if (count($rows) >= $max) {
                break;
            }
        }
        fclose($fh);

        return $rows;
    }
}
