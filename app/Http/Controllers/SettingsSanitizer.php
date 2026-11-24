<?php

namespace App\Http\Controllers;

use App\Support\GameDefaults;

/**
 * Whitelists and type-casts game configuration coming from forms or the in-game settings
 * panel, so arbitrary keys / types can never be stored (mass-assignment protection for JSON).
 */
class SettingsSanitizer
{
    public static function session(array $input): array
    {
        $base = GameDefaults::base();
        $out = [];
        foreach ($base as $group => $defaults) {
            if (! array_key_exists($group, $input)) {
                continue;
            }
            if (array_is_list($defaults)) { // string lists (funny challenges, prizes)
                $lines = is_array($input[$group]) ? $input[$group] : preg_split('/\r?\n/', (string) $input[$group]);
                $out[$group] = array_values(array_filter(array_map(fn ($l) => mb_substr(trim((string) $l), 0, 200), $lines)));
                continue;
            }
            foreach ($defaults as $key => $default) {
                if (! is_array($input[$group]) || ! array_key_exists($key, $input[$group])) {
                    continue;
                }
                $v = $input[$group][$key];
                $out[$group][$key] = match (true) {
                    is_bool($default) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                    is_int($default) => max(-1000, min(100000, (int) $v)),
                    is_float($default) => max(0, min(100, (float) $v)),
                    is_array($default) => array_values(array_filter(array_map('strval', (array) $v), fn ($x) => preg_match('/^[a-z_]{1,30}$/', $x))),
                    default => mb_substr(preg_replace('/[^a-z_]/', '', strtolower((string) $v)), 0, 30),
                };
            }
        }
        // Sanity limits
        if (isset($out['animation'])) {
            foreach (['accel' => [0.1, 3], 'fast' => [0.3, 8], 'question_slow' => [0.5, 8], 'wheel_slow' => [1, 10], 'pause' => [0, 5]] as $k => [$min, $max]) {
                if (isset($out['animation'][$k])) {
                    $out['animation'][$k] = max($min, min($max, (float) $out['animation'][$k]));
                }
            }
        }
        if (isset($out['timer']['default_seconds'])) {
            $out['timer']['default_seconds'] = max(5, min(600, $out['timer']['default_seconds']));
        }
        foreach (['bonus_chance', 'danger_chance', 'mystery_chance'] as $k) {
            if (isset($out['modes'][$k])) {
                $out['modes'][$k] = max(0, min(100, $out['modes'][$k]));
            }
        }

        return $out;
    }
}
