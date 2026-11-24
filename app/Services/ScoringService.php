<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\QuizAnswer;
use App\Models\QuizRound;
use App\Models\QuizSession;
use App\Models\QuizSessionParticipant;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public const RESULTS = ['correct', 'incorrect', 'partial', 'passed', 'timed_out'];

    /**
     * @param  array{override_points?:int|null, seconds_remaining?:int|null, star?:bool}  $opts
     */
    public function score(QuizSession $session, string $result, array $opts = []): QuizAnswer
    {
        if (! in_array($result, self::RESULTS, true)) {
            throw new GameException('Unknown result.', 422);
        }

        return DB::transaction(function () use ($session, $result, $opts) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($s->status === 'completed') {
                throw new GameException('This session has been completed.');
            }
            /** @var QuizRound|null $round */
            $round = $s->rounds()->where('status', 'revealed')->reorder('round_number', 'desc')->lockForUpdate()->first();
            if (! $round) {
                throw new GameException('There is no open round to score.');
            }
            if (! empty($opts['round_id']) && (int) $opts['round_id'] !== $round->id) {
                throw new GameException('This round has already been scored.');
            }

            /** @var QuizSessionParticipant $entry */
            $entry = $s->entries()->where('participant_id', $round->participant_id)->lockForUpdate()->firstOrFail();
            $snapshot = $entry->only(QuizSessionParticipant::STAT_FIELDS);
            $cfg = $s->config('scoring');

            $pa = (int) $round->points_available;
            $isDanger = $round->round_type === 'danger';
            $base = 0;
            $streakBonus = 0;
            $timeBonus = 0;
            $extra = 0;

            switch ($result) {
                case 'correct':
                    $base = $pa;
                    break;
                case 'partial':
                    $base = (int) round($pa * ((int) $cfg['partial_percent']) / 100);
                    break;
                case 'incorrect':
                case 'timed_out':
                    $base = (int) $cfg['incorrect_points'];
                    if ($isDanger && $cfg['danger_negative']) {
                        $base = -(int) round($pa * ((int) $cfg['danger_penalty_percent']) / 100);
                    }
                    break;
                case 'passed':
                    $base = 0;
                    break;
            }

            if ($round->double_or_nothing) {
                if ($result === 'correct') {
                    $base = $pa * 2;
                } elseif (in_array($result, ['incorrect', 'timed_out'], true)) {
                    $base = -$pa;
                }
            }

            // Streaks
            $streak = (int) $entry->current_streak;
            if ($result === 'correct') {
                $streak++;
                if ($cfg['streak_enabled'] && $streak >= (int) $cfg['streak_threshold']) {
                    $streakBonus = (int) $cfg['streak_bonus'];
                }
            } elseif (in_array($result, ['incorrect', 'timed_out'], true)) {
                $streak = 0;
            }

            $secondsRemaining = isset($opts['seconds_remaining']) ? max(0, (int) $opts['seconds_remaining']) : null;
            if ($result === 'correct' && $cfg['time_bonus_enabled'] && $secondsRemaining && $round->timer_seconds) {
                $timeBonus = (int) round((int) $cfg['time_bonus_max'] * min(1, $secondsRemaining / $round->timer_seconds));
            }

            if (! empty($opts['star'])) {
                $extra = (int) $cfg['star_bonus_points'];
            }

            $isOverride = array_key_exists('override_points', $opts) && $opts['override_points'] !== null && $opts['override_points'] !== '';
            if ($isOverride) {
                $base = (int) $opts['override_points'];
                $streakBonus = $timeBonus = $extra = 0;
            }
            $total = $base + $streakBonus + $timeBonus + $extra;

            // Team questions may award the points to the team rather than the individual.
            $teamAward = $round->team_id && $s->config('modes.team_mode')
                && ($round->question?->type === 'team' || $round->mystery_effect === 'team_challenge')
                && $cfg['team_question_award'] === 'team';

            $entry->answered++;
            $field = $result === 'timed_out' ? 'timed_out' : $result;
            $entry->{$field}++;
            $entry->current_streak = $streak;
            $entry->best_streak = max((int) $entry->best_streak, $streak);
            if (! $teamAward) {
                $entry->score += $total;
            }
            $entry->save();

            if ($teamAward) {
                Team::whereKey($round->team_id)->increment('bonus_points', $total);
            }

            $round->update([
                'status' => 'answered',
                'result' => $result,
                'points_awarded' => $total,
                'answered_at' => now(),
            ]);

            $answer = $s->answers()->create([
                'quiz_round_id' => $round->id,
                'participant_id' => $round->participant_id,
                'team_id' => $round->team_id,
                'result' => $result,
                'base_points' => $base,
                'streak_bonus' => $streakBonus,
                'time_bonus' => $timeBonus,
                'extra_bonus' => $extra,
                'points' => $total,
                'is_override' => $isOverride,
                'seconds_remaining' => $secondsRemaining,
                'snapshot' => ['entry' => $snapshot, 'team_award' => $teamAward],
                'recorded_by' => auth()->id(),
            ]);

            $s->logEvent('scored', ['round' => $round->round_number, 'result' => $result, 'points' => $total]);

            return $answer->setRelation('round', $round);
        });
    }

    /** Reverse the most recent live scoring action and re-open its round. */
    public function undo(QuizSession $session): QuizRound
    {
        return DB::transaction(function () use ($session) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($s->status === 'completed') {
                throw new GameException('This session has been completed.');
            }
            if ($s->rounds()->where('status', 'revealed')->exists()) {
                throw new GameException('Finish or cancel the current round before undoing an earlier score.');
            }
            /** @var QuizAnswer|null $answer */
            $answer = $s->answers()->live()->latest('id')->lockForUpdate()->first();
            if (! $answer) {
                throw new GameException('Nothing to undo.');
            }

            $entry = $s->entries()->where('participant_id', $answer->participant_id)->lockForUpdate()->first();
            if ($entry) {
                foreach (($answer->snapshot['entry'] ?? []) as $k => $v) {
                    if (in_array($k, QuizSessionParticipant::STAT_FIELDS, true)) {
                        $entry->{$k} = $v;
                    }
                }
                $entry->save();
            }
            if (! empty($answer->snapshot['team_award']) && $answer->team_id) {
                Team::whereKey($answer->team_id)->decrement('bonus_points', $answer->points);
            }

            $round = $answer->round;
            $round->update(['status' => 'revealed', 'result' => null, 'points_awarded' => null, 'answered_at' => null]);
            $answer->update(['undone_at' => now()]);
            $s->logEvent('undo', ['round' => $round->round_number, 'points' => $answer->points]);

            return $round;
        });
    }

    public function setDoubleOrNothing(QuizSession $session, bool $on): QuizRound
    {
        return DB::transaction(function () use ($session, $on) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $s->config('modes.double_or_nothing_enabled')) {
                throw new GameException('Double or Nothing is disabled for this session.', 422);
            }
            $round = $s->rounds()->where('status', 'revealed')->reorder('round_number', 'desc')->first();
            if (! $round) {
                throw new GameException('There is no open round.');
            }
            $round->update(['double_or_nothing' => $on]);
            $s->logEvent('double_or_nothing', ['round' => $round->round_number, 'on' => $on]);

            return $round;
        });
    }
}
