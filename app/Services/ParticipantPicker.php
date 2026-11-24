<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\QuizSession;
use App\Models\QuizSessionParticipant;
use Illuminate\Support\Collection;

/**
 * Server-side, auditable participant selection. The front-end wheel only animates
 * towards the participant chosen here.
 */
class ParticipantPicker
{
    /** @return Collection<QuizSessionParticipant> */
    public function eligible(QuizSession $session): Collection
    {
        return $session->entries()
            ->where('is_excluded', false)
            ->whereHas('participant', fn ($q) => $q->where('is_active', true))
            ->with('participant')
            ->orderBy('id')
            ->get();
    }

    public function pick(QuizSession $session, ?int $requestedParticipantId = null, ?int $lastParticipantId = null): QuizSessionParticipant
    {
        $eligible = $this->eligible($session);
        if ($eligible->isEmpty()) {
            throw new GameException('There are no eligible participants. Add participants or remove exclusions in Settings.', 422);
        }

        $mode = $session->selection_mode;
        $allowRepeat = (bool) $session->config('modes.allow_repeat_participant');

        if ($requestedParticipantId) {
            $chosen = $eligible->firstWhere('participant_id', $requestedParticipantId);
            if (! $chosen) {
                throw new GameException('That participant is not eligible (inactive or excluded).', 422);
            }

            return $chosen;
        }

        if ($mode === 'facilitator') {
            throw new GameException('Facilitator selection mode: please choose the participant before spinning.', 422);
        }

        $pool = match ($mode) {
            'random' => $eligible,
            'fair_rotation' => $this->lowest($eligible, 'answered'),
            'remaining' => $this->lowest($eligible, 'times_selected'),
            'team' => $this->teamPool($eligible),
            default => $eligible,
        };

        if (! $allowRepeat && $lastParticipantId && $pool->count() > 1) {
            $pool = $pool->reject(fn ($e) => $e->participant_id === $lastParticipantId)->values();
        } elseif (! $allowRepeat && $lastParticipantId && $pool->count() === 1 && $eligible->count() > 1
            && $pool->first()->participant_id === $lastParticipantId) {
            // Rotation would pick the same person twice in a row – take the next-lowest instead.
            $pool = $eligible->reject(fn ($e) => $e->participant_id === $lastParticipantId)->values();
            $pool = $mode === 'fair_rotation' ? $this->lowest($pool, 'answered') : $this->lowest($pool, 'times_selected');
        }

        return $this->randomFrom($pool);
    }

    private function lowest(Collection $entries, string $field): Collection
    {
        $min = $entries->min($field);

        return $entries->filter(fn ($e) => (int) $e->{$field} === (int) $min)->values();
    }

    private function teamPool(Collection $eligible): Collection
    {
        $byTeam = $eligible->whereNotNull('team_id')->groupBy('team_id');
        if ($byTeam->isEmpty()) {
            return $this->lowest($eligible, 'times_selected');
        }
        $teamIds = $byTeam->keys()->values();
        $teamId = $teamIds[random_int(0, $teamIds->count() - 1)];

        return $this->lowest($byTeam[$teamId], 'times_selected');
    }

    private function randomFrom(Collection $pool): QuizSessionParticipant
    {
        $pool = $pool->values();

        return $pool[random_int(0, $pool->count() - 1)];
    }
}
