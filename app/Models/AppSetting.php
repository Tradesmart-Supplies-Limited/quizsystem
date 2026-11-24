<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    protected $casts = ['value' => 'array'];

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever('app_settings', fn () => static::query()->pluck('value', 'key')->map(
            fn ($v) => is_string($v) ? json_decode($v, true) : $v
        )->all());

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function put(string $key, $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget('app_settings');
    }
}
