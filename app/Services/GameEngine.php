<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Question;
use App\Models\QuizRound;
use App\Models\QuizSession;
use Illuminate\Support\Facades\DB;

class GameEngine
{
    public const MYSTERY_EFFECTS = [
        'normal' => 'Normal question',
        'bonus' => 'Bonus question',
        'practical' => 'Practical task',
        'team_challenge' => 'Team challenge',
        'double_points' => 'Double points',
        'funny_challenge' => 'Funny challenge',
        'no_points' => 'No points – just for glory!',
        'mystery_prize' => 'Mystery prize',
    ];

    public function __construct(
        private ParticipantPicker $participants,
        private QuestionPicker $questions,
    ) {}

    /**
     * Create the next round. Runs inside a transaction with a row lock on the session,
     * so double clicks / two tabs can never create duplicate rounds.
     *
     * @param  array{round_number?:int, participant_id?:int}  $opts
     */
    public function spin(QuizSession $session, array $opts = []): QuizRound
    {
        return DB::transaction(function () use ($session, $opts) {
            /** @var QuizSession $s */
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            // Idempotency: the client tells us which round it believes it is spinning.
            if (! empty($opts['round_number'])) {
                $existing = $s->rounds()->where('round_number', $opts['round_number'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($s->status === 'paused') {
                throw new GameException('The session is paused. Resume it to continue.');
            }
            if (! in_array($s->status, ['ready', 'active'], true)) {
                throw new GameException('This session is not running.');
            }
            if ($s->rounds()->where('status', 'revealed')->exists()) {
                throw new GameException('Record a result for the current round before spinning again.');
            }
            if ($s->current_round >= $s->total_rounds) {
                throw new GameException('All rounds have been played. Time to crown the champion!');
            }

            if ($s->status === 'ready') {
                $s->status = 'active';
                $s->started_at ??= now();
            }

            $roundNumber = $s->current_round + 1;
            $last = $s->rounds()->reorder('round_number', 'desc')->first();
            $cfg = $s->config();

            // ---- Decide the round type -------------------------------------------------
            $roundType = 'normal';
            $mystery = null;
            $multiplier = 1.0;
            $challenge = null;
            $hotSeat = $s->stateValue('hot_seat');
            $pending = $s->stateValue('pending_mode');
            $speedLeft = (int) $s->stateValue('speed_remaining', 0);
            $forcedParticipant = $opts['participant_id'] ?? null;
            $hotSeatIndex = null;

            if ($hotSeat && ($hotSeat['remaining'] ?? 0) > 0 && ! empty($hotSeat['participant_id'])) {
                $roundType = 'hot_seat';
                $forcedParticipant = $hotSeat['participant_id'];
                $hotSeatIndex = ($hotSeat['total'] - $hotSeat['remaining']) + 1;
                $hotSeat['remaining']--;
                $s->setStateValue('hot_seat', $hotSeat['remaining'] > 0 ? $hotSeat : null);
            } elseif ($pending === 'hot_seat') {
                $roundType = 'hot_seat';
                $hotSeatIndex = 1;
                $total = max(1, (int) $cfg['modes']['hot_seat_questions']);
                $s->setStateValue('pending_mode', null);
                // participant filled in after the pick below
                $s->setStateValue('hot_seat', ['participant_id' => null, 'remaining' => $total - 1, 'total' => $total]);
            } elseif (in_array($pending, ['bonus', 'danger', 'mystery'], true)) {
                $roundType = $pending;
                $s->setStateValue('pending_mode', null);
            } else {
                $roll = random_int(1, 100);
                $m = $cfg['modes'];
                $b = $m['bonus_enabled'] ? (int) $m['bonus_chance'] : 0;
                $d = $m['danger_enabled'] ? (int) $m['danger_chance'] : 0;
                $y = $m['mystery_enabled'] ? (int) $m['mystery_chance'] : 0;
                if ($roll <= $b) {
                    $roundType = 'bonus';
                } elseif ($roll <= $b + $d) {
                    $roundType = 'danger';
                } elseif ($roll <= $b + $d + $y) {
                    $roundType = 'mystery';
                }
            }

            $isSpeed = $speedLeft > 0;
            if ($isSpeed) {
                $s->setStateValue('speed_remaining', $speedLeft - 1);
                if ($roundType === 'normal') {
                    $roundType = 'speed';
                }
            }

            $preference = null;
            if ($roundType === 'bonus') {
                $multiplier = (float) $cfg['scoring']['bonus_multiplier'];
                $preference = 'bonus';
            } elseif ($roundType === 'danger') {
                $multiplier = (float) $cfg['scoring']['danger_multiplier'];
                $preference = 'danger';
            } elseif ($roundType === 'mystery') {
                $keys = array_keys(self::MYSTERY_EFFECTS);
                $mystery = $keys[random_int(0, count($keys) - 1)];
                [$multiplier, $preference, $challenge] = match ($mystery) {
                    'bonus' => [(float) $cfg['scoring']['bonus_multiplier'], 'bonus', null],
                    'practical' => [1.0, 'practical', null],
                    'team_challenge' => [1.0, 'team', null],
                    'double_points' => [2.0, null, null],
                    'funny_challenge' => [1.0, null, $this->randomLine($cfg['funny_challenges'])],
                    'no_points' => [0.0, null, null],
                    'mystery_prize' => [1.0, null, $this->randomLine($cfg['mystery_prizes'])],
                    default => [1.0, 'mystery', null],
                };
            }

            // ---- Question --------------------------------------------------------------
            $question = null;
            if ($mystery !== 'funny_challenge') {
                $question = $this->questions->pick($s, $preference, $last?->question_id);
            }

            // ---- Participant -----------------------------------------------------------
            $entry = $this->participants->pick($s, $forcedParticipant ? (int) $forcedParticipant : null, $roundType === 'hot_seat' ? null : $last?->participant_id);

            if ($roundType === 'hot_seat' && $hotSeatIndex === 1) {
                $hs = $s->stateValue('hot_seat');
                if ($hs) {
                    $hs['participant_id'] = $entry->participant_id;
                    $s->setStateValue('hot_seat', $hs);
                }
            }

            $basePoints = $question?->points ?? 10;
            $timer = null;
            if ($cfg['timer']['enabled']) {
                $timer = $isSpeed ? (int) $cfg['modes']['speed_timer'] : (int) ($question?->time_limit ?: $cfg['timer']['default_seconds']);
            }

            $round = $s->rounds()->create([
                'round_number' => $roundNumber,
                'question_id' => $question?->id,
                'participant_id' => $entry->participant_id,
                'team_id' => $entry->team_id,
                'round_type' => $roundType,
                'mystery_effect' => $mystery,
                'challenge_text' => $challenge,
                'multiplier' => $multiplier,
                'points_available' => (int) round($basePoints * $multiplier),
                'timer_seconds' => $timer,
                'hot_seat_index' => $hotSeatIndex,
                'selection_mode' => $forcedParticipant && $roundType !== 'hot_seat' ? 'facilitator' : $s->selection_mode,
                'status' => 'revealed',
            ]);

            $entry->increment('times_selected', 1, ['last_selected_round' => $roundNumber]);
            if ($question) {
                Question::whereKey($question->id)->update([
                    'times_used' => DB::raw('times_used + 1'),
                    'last_used_at' => now(),
                ]);
            }

            $s->current_round = $roundNumber;
            $s->save();
            $s->logEvent('round_created', [
                'round' => $roundNumber, 'question_id' => $question?->id,
                'participant_id' => $entry->participant_id, 'type' => $roundType, 'mystery' => $mystery,
            ]);

            return $round;
        });
    }

    /** Discard an un-scored round (e.g. participant absent) and give the round number back. */
    public function void(QuizSession $session): void
    {
        DB::transaction(function () use ($session) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $round = $s->rounds()->where('status', 'revealed')->reorder('round_number', 'desc')->first();
            if (! $round) {
                throw new GameException('There is no open round to cancel.');
            }
            $s->entries()->where('participant_id', $round->participant_id)
                ->where('times_selected', '>', 0)->decrement('times_selected');
            if ($round->question_id) {
                Question::whereKey($round->question_id)->where('times_used', '>', 0)->decrement('times_used');
            }
            if ($round->round_type === 'hot_seat') {
                $hs = $s->stateValue('hot_seat');
                if ($hs && $round->hot_seat_index === 1) {
                    $s->setStateValue('hot_seat', null);
                } elseif ($hs) {
                    $hs['remaining']++;
                    $s->setStateValue('hot_seat', $hs);
                }
            }
            $s->current_round = max(0, $s->current_round - 1);
            $s->save();
            $s->logEvent('round_voided', ['round' => $round->round_number]);
            $round->delete();
        });
    }

    public function queueMode(QuizSession $session, string $mode): void
    {
        DB::transaction(function () use ($session, $mode) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($mode === 'speed') {
                $s->setStateValue('speed_remaining', (int) $s->config('modes.speed_rounds'));
            } elseif ($mode === 'cancel') {
                $s->setStateValue('pending_mode', null);
                $s->setStateValue('speed_remaining', 0);
                $s->setStateValue('hot_seat', null);
            } else {
                $s->setStateValue('pending_mode', $mode);
            }
            $s->save();
            $s->logEvent('mode_queued', ['mode' => $mode]);
        });
    }

    public function setStatus(QuizSession $session, string $action): QuizSession
    {
        return DB::transaction(function () use ($session, $action) {
            $s = QuizSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            switch ($action) {
                case 'start':
                    if (! in_array($s->status, ['draft', 'ready', 'paused'], true)) {
                        break;
                    }
                    if ($s->entries()->count() < 1) {
                        throw new GameException('Add at least one participant before starting.', 422);
                    }
                    $s->status = 'active';
                    $s->started_at ??= now();
                    $s->paused_at = null;
                    break;
                case 'pause':
                    if ($s->status === 'active') {
                        $s->status = 'paused';
                        $s->paused_at = now();
                    }
                    break;
                case 'resume':
                    if ($s->status === 'paused') {
                        $s->status = 'active';
                        $s->paused_at = null;
                    }
                    break;
                case 'complete':
                    $open = $s->rounds()->where('status', 'revealed')->first();
                    if ($open) {
                        // An unscored final round is discarded so the results stay clean.
                        $this->void($s);
                        $s->refresh();
                    }
                    $s->status = 'completed';
                    $s->ended_at = now();
                    break;
                case 'reopen':
                    if ($s->status === 'completed') {
                        $s->status = 'paused';
                        $s->ended_at = null;
                    }
                    break;
            }
            $s->save();
            $s->logEvent('status_'.$action, ['status' => $s->status]);

            return $s;
        });
    }

    private function randomLine(array $lines): ?string
    {
        $lines = array_values(array_filter($lines));

        return $lines ? $lines[random_int(0, count($lines) - 1)] : null;
    }
}
