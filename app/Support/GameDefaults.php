<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Central definition of every configurable game option and its default value.
 * Global defaults can be overridden from Admin › Settings (stored in app_settings),
 * and each quiz session can override them again (quiz_sessions.settings).
 */
class GameDefaults
{
    public static function base(): array
    {
        return [
            'timer' => [
                'enabled' => true,
                'default_seconds' => 30,
                'warning_at' => 10,      // yellow + ticking
                'critical_at' => 5,      // red + fast ticking + pulse
                'ticking' => true,
                'auto_start' => false,
                'auto_timeout' => false, // automatically record "timed out" at zero
            ],
            'scoring' => [
                'incorrect_points' => 0,
                'partial_percent' => 50,
                'bonus_multiplier' => 2,
                'danger_multiplier' => 2,
                'danger_negative' => true,
                'danger_penalty_percent' => 50,
                'streak_enabled' => true,
                'streak_threshold' => 3,
                'streak_bonus' => 5,
                'time_bonus_enabled' => false,
                'time_bonus_max' => 5,
                'star_bonus_points' => 5,
                'team_question_award' => 'team', // team | participant
            ],
            'modes' => [
                'bonus_enabled' => true,
                'bonus_chance' => 12,
                'danger_enabled' => true,
                'danger_chance' => 8,
                'mystery_enabled' => true,
                'mystery_chance' => 6,
                'double_or_nothing_enabled' => true,
                'hot_seat_questions' => 3,
                'speed_rounds' => 5,
                'speed_timer' => 10,
                'team_mode' => false,
                'allow_repeat_participant' => false,
                'allow_repeat_questions' => false,
                'difficulties' => ['easy', 'medium', 'hard'],
                'types' => [],
            ],
            'animation' => [
                'accel' => 0.5,
                'fast' => 2.0,
                'question_slow' => 2.5,
                'wheel_slow' => 4.0,
                'pause' => 1.0,
                'intensity' => 'normal', // low | normal | high
                'reduce_motion' => false,
            ],
            'display' => [
                'show_leaderboard' => false,
                'show_photos' => true,
                'show_logo' => true,
                'show_date' => true,
                'projector_mode' => false,
            ],
            'audio' => [
                'enabled' => true,
                'sfx_enabled' => true,
                'music_enabled' => false,
                'sfx_volume' => 0.8,
                'music_volume' => 0.25,
                'theme' => 'gameshow',
            ],
            'funny_challenges' => [
                'Describe your job using only three words!',
                'Do your best impression of a forklift reversing!',
                'Name five things you can find in our warehouse in 10 seconds!',
                'Give the team a 15-second motivational speech!',
                'Explain "just-in-time delivery" to a five-year-old!',
                'Pitch the company stapler as if it were a luxury product!',
            ],
            'mystery_prizes' => [
                'Mystery prize: first pick of the snacks!',
                'Mystery prize: you choose who answers next round\'s bonus!',
                'Mystery prize: a round of applause from the whole room!',
            ],
        ];
    }

    /** Global defaults (code defaults overlaid with admin-saved defaults). */
    public static function session(): array
    {
        try {
            $saved = AppSetting::get('game_defaults', []);
        } catch (\Throwable) {
            $saved = [];
        }

        return self::mergeConfig(self::base(), is_array($saved) ? $saved : []);
    }

    /** Recursive merge where list arrays are replaced (not merged index-by-index). */
    public static function mergeConfig(array $base, array $over): array
    {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && ! array_is_list($base[$k]) && ! array_is_list($v)) {
                $base[$k] = self::mergeConfig($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }

        return $base;
    }

    /** Sound catalogue: key => [label, group]. Files live in public/sounds/{theme}/{key}.mp3 */
    public static function sounds(): array
    {
        return [
            'game_start' => ['Game starting', 'Start'],
            'spin_start' => ['Spin starting', 'Start'],
            'drumroll' => ['Drumroll', 'Start'],
            'suspense' => ['Suspense build-up', 'Start'],
            'countdown_start' => ['Countdown start', 'Start'],
            'wheel_spin' => ['Wheel spinning', 'Wheel'],
            'wheel_tick' => ['Mechanical tick', 'Wheel'],
            'wheel_slow' => ['Wheel slowing down', 'Wheel'],
            'wheel_stop' => ['Final wheel stop', 'Wheel'],
            'slot_spin' => ['Slot machine spin', 'Question'],
            'question_reveal' => ['Question reveal', 'Question'],
            'question_select' => ['Question selection', 'Question'],
            'bonus_reveal' => ['Bonus question reveal', 'Question'],
            'correct' => ['Correct answer', 'Success'],
            'applause' => ['Applause', 'Success'],
            'victory' => ['Victory', 'Success'],
            'jackpot' => ['Jackpot', 'Success'],
            'level_up' => ['Level up', 'Success'],
            'streak' => ['Streak achieved', 'Success'],
            'wrong' => ['Wrong answer', 'Danger'],
            'buzzer' => ['Buzzer', 'Danger'],
            'time_low' => ['Clock tick (time running out)', 'Danger'],
            'times_up' => ["Time's up", 'Danger'],
            'danger_round' => ['Danger round', 'Danger'],
            'dramatic' => ['Dramatic suspense', 'Danger'],
            'comedic_fail' => ['Comedic fail', 'Funny'],
            'boing' => ['Cartoon boing', 'Funny'],
            'record_scratch' => ['Record scratch', 'Funny'],
            'funny_horn' => ['Funny horn', 'Funny'],
            'sad_trombone' => ['Sad trombone', 'Funny'],
            'surprise' => ['Unexpected surprise', 'Funny'],
            'laugh' => ['Laughing', 'Funny'],
            'disappointment' => ['Playful disappointment', 'Funny'],
            'confetti' => ['Confetti pop', 'Celebration'],
            'winner' => ['Winner announcement', 'Celebration'],
            'championship' => ['Championship victory', 'Celebration'],
            'final_celebration' => ['Final quiz celebration', 'Celebration'],
            'music_lobby' => ['Background music (loop)', 'Music'],
        ];
    }

    public static function soundThemes(): array
    {
        return ['gameshow' => 'Game Show (orchestral synth)', 'arcade' => 'Arcade (8-bit retro)'];
    }

    /** Which sound(s) play for each game event. Arrays = pick one at random. */
    public static function soundEvents(): array
    {
        return [
            'intro' => ['game_start'],
            'spin' => ['spin_start'],
            'slot_loop' => ['slot_spin'],
            'wheel_tick' => ['wheel_tick'],
            'question_stop' => ['question_select'],
            'suspense' => ['drumroll'],
            'wheel_stop' => ['wheel_stop'],
            'reveal' => ['question_reveal'],
            'bonus' => ['jackpot', 'bonus_reveal'],
            'danger' => ['danger_round'],
            'mystery' => ['surprise'],
            'final_round' => ['dramatic'],
            'timer_start' => ['countdown_start'],
            'timer_tick' => ['time_low'],
            'times_up' => ['times_up'],
            'correct' => ['correct'],
            'correct_extra' => ['applause', 'level_up', 'victory'],
            'streak' => ['streak'],
            'incorrect' => ['wrong', 'buzzer'],
            'incorrect_funny' => ['sad_trombone', 'comedic_fail', 'boing', 'record_scratch', 'funny_horn', 'laugh', 'disappointment'],
            'pass' => ['boing'],
            'undo' => ['record_scratch'],
            'winner' => ['championship', 'final_celebration'],
            'confetti' => ['confetti'],
        ];
    }

    public static function audioConfig(): array
    {
        try {
            $map = AppSetting::get('sound_events', []);
            $disabled = AppSetting::get('sound_disabled', []);
        } catch (\Throwable) {
            $map = [];
            $disabled = [];
        }

        return [
            'events' => array_replace(self::soundEvents(), is_array($map) ? $map : []),
            'disabled' => array_values(is_array($disabled) ? $disabled : []),
            'catalogue' => self::sounds(),
            'themes' => self::soundThemes(),
        ];
    }
}
