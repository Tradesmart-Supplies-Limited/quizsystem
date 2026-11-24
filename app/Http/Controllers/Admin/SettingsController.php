<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SettingsSanitizer;
use App\Models\AppSetting;
use App\Support\GameDefaults;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'config' => GameDefaults::session(),
            'audio' => GameDefaults::audioConfig(),
            'branding' => AppSetting::get('branding', ['company' => 'TradeSmart Supplies Limited', 'tagline' => 'Together Values']),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => ['nullable', 'array'],
            'events' => ['nullable', 'array'],
            'disabled' => ['nullable', 'array'],
            'branding.company' => ['nullable', 'string', 'max:100'],
            'branding.tagline' => ['nullable', 'string', 'max:100'],
        ]);

        $input = $request->input('settings', []);
        foreach (GameDefaults::base() as $group => $defaults) {
            if (array_is_list($defaults) || ! isset($input[$group])) {
                continue;
            }
            foreach ($defaults as $k => $v) {
                if (is_bool($v)) {
                    $input[$group][$k] = ! empty($input[$group][$k]);
                }
            }
        }
        AppSetting::put('game_defaults', SettingsSanitizer::session($input), 'game');

        $catalogue = array_keys(GameDefaults::sounds());
        $events = [];
        foreach (array_keys(GameDefaults::soundEvents()) as $event) {
            $chosen = array_values(array_intersect((array) $request->input("events.$event", []), $catalogue));
            if ($chosen) {
                $events[$event] = $chosen;
            }
        }
        AppSetting::put('sound_events', $events, 'audio');
        AppSetting::put('sound_disabled', array_values(array_intersect((array) $request->input('disabled', []), $catalogue)), 'audio');
        AppSetting::put('branding', [
            'company' => $request->input('branding.company') ?: 'TradeSmart Supplies Limited',
            'tagline' => $request->input('branding.tagline') ?: 'Together Values',
        ], 'branding');

        return back()->with('success', 'Default settings saved. New sessions will use these defaults.');
    }
}
