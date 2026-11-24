<?php

namespace App\Services;

use App\Models\QuizRound;
use App\Models\QuizSession;
use App\Models\QuizSessionParticipant;
use App\Models\ReactionMessage;
use App\Support\GameDefaults;
use Illuminate\Support\Collection;

/** Builds the JSON payloads consumed by the game screen. */
class GameState
{
    public function __construct(private QuestionPicker $questions) {}

    public function full(QuizSession $s): array
    {
        $s->refresh();
        $current = $s->rounds()->reorder('round_number', 'desc')->with(['question.category', 'question.options', 'participant', 'team'])->first();

        return [
            'session' => $this->sessionInfo($s),
            'config' => $s->config(),
            'participants' => $this->participants($s),
            'teams' => $this->teams($s),
            'leaderboard' => $this->leaderboard($s),
            'team_leaderboard' => $this->teamLeaderboard($s),
            'round' => $current ? $this->round($current) : null,
            'history' => $this->history($s),
            'can_undo' => $s->answers()->live()->exists() && ! $s->rounds()->where('status', 'revealed')->exists(),
            'reactions' => $this->reactions(),
            'audio' => GameDefaults::audioConfig(),
        ];
    }

    /** Lightweight payload after scoring/undo. */
    public function scores(QuizSession $s): array
    {
        $s->refresh();
        $current = $s->rounds()->reorder('round_number', 'desc')->with(['question.category', 'question.options', 'participant', 'team'])->first();

        return [
            'session' => $this->sessionInfo($s),
            'participants' => $this->participants($s),
            'leaderboard' => $this->leaderboard($s),
            'team_leaderboard' => $this->teamLeaderboard($s),
            'teams' => $this->teams($s),
            'round' => $current ? $this->round($current) : null,
            'history' => $this->history($s),
            'can_undo' => $s->answers()->live()->exists() && ! $s->rounds()->where('status', 'revealed')->exists(),
        ];
    }

    public function sessionInfo(QuizSession $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->title,
            'workshop_title' => $s->workshop_title,
            'session_date' => $s->session_date?->format('j M Y'),
            'status' => $s->status,
            'selection_mode' => $s->selection_mode,
            'total_rounds' => $s->total_rounds,
            'current_round' => $s->current_round,
            'is_final_round' => $s->current_round >= $s->total_rounds,
            'finished' => $s->current_round >= $s->total_rounds && ! $s->rounds()->where('status', 'revealed')->exists(),
            'pending_mode' => $s->stateValue('pending_mode'),
            'speed_remaining' => (int) $s->stateValue('speed_remaining', 0),
            'hot_seat' => $s->stateValue('hot_seat'),
            'remaining_questions' => $this->questions->baseQuery($s)->count(),
            'category_ids' => $s->categories()->pluck('question_categories.id')->all(),
        ];
    }

    public function participants(QuizSession $s): array
    {
        return $s->entries()->with(['participant', 'team'])->orderBy('id')->get()
            ->filter(fn ($e) => $e->participant)
            ->map(fn (QuizSessionParticipant $e) => [
                'id' => $e->participant_id,
                'name' => $e->participant->display_name,
                'full_name' => $e->participant->full_name,
                'initials' => $e->participant->initials,
                'photo' => $e->participant->photo_url,
                'department' => $e->participant->department,
                'active' => (bool) $e->participant->is_active && ! $e->participant->trashed(),
                'excluded' => (bool) $e->is_excluded,
                'team_id' => $e->team_id,
                'team_color' => $e->team?->color,
                'score' => (int) $e->score,
                'answered' => (int) $e->answered,
                'correct' => (int) $e->correct,
                'times_selected' => (int) $e->times_selected,
                'streak' => (int) $e->current_streak,
            ])->values()->all();
    }

    public function teams(QuizSession $s): array
    {
        return $s->teams()->withCount('members')->orderBy('id')->get()->map(fn ($t) => [
            'id' => $t->id, 'name' => $t->name, 'color' => $t->color, 'members' => $t->members_count,
        ])->all();
    }

    public function leaderboard(QuizSession $s): array
    {
        $rows = $s->entries()->with(['participant', 'team'])->get()->filter(fn ($e) => $e->participant);

        return $this->rank($rows)->map(fn ($e) => [
            'rank' => $e->rank,
            'id' => $e->participant_id,
            'name' => $e->participant->display_name,
            'full_name' => $e->participant->full_name,
            'photo' => $e->participant->photo_url,
            'initials' => $e->participant->initials,
            'team' => $e->team?->name,
            'team_color' => $e->team?->color,
            'score' => (int) $e->score,
            'correct' => (int) $e->correct,
            'answered' => (int) $e->answered,
            'accuracy' => $e->accuracy(),
            'best_streak' => (int) $e->best_streak,
        ])->values()->all();
    }

    /** Sort by score, then correct answers, then accuracy; equal values share a rank. */
    public function rank(Collection $rows): Collection
    {
        $sorted = $rows->sort(function ($a, $b) {
            return [$b->score, $b->correct, $b->accuracy(), $a->participant?->display_name]
                <=> [$a->score, $a->correct, $a->accuracy(), $b->participant?->display_name];
        })->values();
        $rank = 0;
        $prev = null;
        foreach ($sorted as $i => $e) {
            $key = $e->score.'|'.$e->correct;
            if ($key !== $prev) {
                $rank = $i + 1;
                $prev = $key;
            }
            $e->rank = $rank;
        }

        return $sorted;
    }

    public function teamLeaderboard(QuizSession $s): array
    {
        $teams = $s->teams()->with('members')->get()->map(function ($t) {
            $correct = $t->members->sum('correct');
            $answered = $t->members->sum('answered');

            return [
                'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
                'score' => (int) $t->members->sum('score') + (int) $t->bonus_points,
                'correct' => (int) $correct, 'answered' => (int) $answered,
                'members' => $t->members->count(),
                'accuracy' => $answered ? round($correct / $answered * 100, 1) : 0,
            ];
        })->sortByDesc('score')->values();

        return $teams->map(fn ($t, $i) => $t + ['rank' => $i + 1])->all();
    }

    public function round(QuizRound $r): array
    {
        $q = $r->question;

        return [
            'id' => $r->id,
            'number' => $r->round_number,
            'type' => $r->round_type,
            'mystery_effect' => $r->mystery_effect,
            'mystery_label' => $r->mystery_effect ? (GameEngine::MYSTERY_EFFECTS[$r->mystery_effect] ?? null) : null,
            'challenge_text' => $r->challenge_text,
            'multiplier' => (float) $r->multiplier,
            'points' => (int) $r->points_available,
            'timer' => $r->timer_seconds,
            'double_or_nothing' => (bool) $r->double_or_nothing,
            'hot_seat_index' => $r->hot_seat_index,
            'status' => $r->status,
            'result' => $r->result,
            'points_awarded' => $r->points_awarded,
            'participant' => $r->participant ? [
                'id' => $r->participant->id,
                'name' => $r->participant->display_name,
                'full_name' => $r->participant->full_name,
                'photo' => $r->participant->photo_url,
                'initials' => $r->participant->initials,
                'department' => $r->participant->department,
                'job_title' => $r->participant->job_title,
            ] : null,
            'team' => $r->team ? ['id' => $r->team->id, 'name' => $r->team->name, 'color' => $r->team->color] : null,
            'question' => $q ? [
                'id' => $q->id,
                'text' => $q->question_text,
                'type' => $q->type,
                'type_label' => $q->type_label,
                'difficulty' => $q->difficulty,
                'category' => $q->category?->name,
                'category_color' => $q->category?->color,
                'category_icon' => $q->category?->icon,
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
                'options' => $q->options->map(fn ($o) => ['label' => $o->label, 'text' => $o->option_text, 'correct' => $o->is_correct])->all(),
            ] : null,
        ];
    }

    public function history(QuizSession $s, int $limit = 12): array
    {
        return $s->rounds()->with(['participant', 'question.category'])->reorder('round_number', 'desc')->limit($limit)->get()
            ->map(fn ($r) => [
                'number' => $r->round_number,
                'participant' => $r->participant?->display_name,
                'category' => $r->question?->category?->name ?? ($r->mystery_effect ? (GameEngine::MYSTERY_EFFECTS[$r->mystery_effect] ?? 'Challenge') : 'Challenge'),
                'type' => $r->round_type,
                'result' => $r->result,
                'points' => $r->points_awarded,
            ])->all();
    }

    public function reactions(): array
    {
        $rows = ReactionMessage::where('is_active', true)->get(['type', 'message']);

        return [
            'correct' => $rows->where('type', 'correct')->pluck('message')->values()->all() ?: ['CORRECT!'],
            'incorrect' => $rows->where('type', 'incorrect')->pluck('message')->values()->all() ?: ['NOT QUITE!'],
        ];
    }

    /** Statistics for the winner celebration / final report. */
    public function finalStats(QuizSession $s): array
    {
        $entries = $s->entries()->with('participant')->get()->filter(fn ($e) => $e->participant);
        $answers = $s->answers()->live()->get();
        $pick = fn ($field) => ($e = $entries->sortByDesc($field)->first()) && $e->{$field} > 0
            ? ['name' => $e->participant->display_name, 'value' => $e->{$field}] : null;
        $acc = $entries->filter(fn ($e) => $e->answered >= 2)->sortByDesc(fn ($e) => $e->accuracy())->first();

        return [
            'rounds_played' => $s->rounds()->where('status', 'answered')->count(),
            'total_correct' => $answers->where('result', 'correct')->count(),
            'total_incorrect' => $answers->whereIn('result', ['incorrect', 'timed_out'])->count(),
            'total_points' => (int) $answers->sum('points'),
            'average_score' => $entries->count() ? round($entries->avg('score'), 1) : 0,
            'most_correct' => $pick('correct'),
            'longest_streak' => $pick('best_streak'),
            'most_active' => $pick('answered'),
            'highest_accuracy' => $acc ? ['name' => $acc->participant->display_name, 'value' => $acc->accuracy().'%'] : null,
        ];
    }
}
