<?php

namespace App\Services;

use App\Exceptions\GameException;
use App\Models\Question;
use App\Models\QuizSession;
use Illuminate\Database\Eloquent\Builder;

class QuestionPicker
{
    public function baseQuery(QuizSession $session, bool $excludeUsed = true): Builder
    {
        $q = Question::query()->active()
            ->whereHas('category', fn ($c) => $c->where('is_active', true));

        $categoryIds = $session->categories()->pluck('question_categories.id');
        if ($categoryIds->isNotEmpty()) {
            $q->whereIn('question_category_id', $categoryIds);
        }

        $poolIds = $session->questionPool()->pluck('questions.id');
        if ($poolIds->isNotEmpty()) {
            $q->whereIn('questions.id', $poolIds);
        }

        $difficulties = array_filter((array) $session->config('modes.difficulties', []));
        if ($difficulties) {
            $q->whereIn('difficulty', $difficulties);
        }

        $types = array_filter((array) $session->config('modes.types', []));
        if ($types) {
            $q->whereIn('type', $types);
        }

        if ($excludeUsed && ! $session->config('modes.allow_repeat_questions')) {
            $used = $session->rounds()->whereNotNull('question_id')->pluck('question_id');
            if ($used->isNotEmpty()) {
                $q->whereNotIn('questions.id', $used);
            }
        }

        return $q;
    }

    /**
     * @param  string|null  $preference  bonus | danger | practical | team
     */
    public function pick(QuizSession $session, ?string $preference = null, ?int $lastQuestionId = null): Question
    {
        $base = $this->baseQuery($session);

        if ($preference) {
            $preferred = (clone $base);
            match ($preference) {
                'bonus' => $preferred->where(fn ($w) => $w->where('is_bonus', true)->orWhere('type', 'bonus')),
                'danger' => $preferred->where('is_danger', true),
                'practical' => $preferred->whereIn('type', ['practical', 'scenario']),
                'team' => $preferred->where('type', 'team'),
                'mystery' => $preferred->where('type', 'mystery'),
                default => null,
            };
            $ids = $preferred->pluck('questions.id');
            if ($ids->isNotEmpty()) {
                return Question::with(['category', 'options'])->findOrFail($ids[random_int(0, $ids->count() - 1)]);
            }
        }

        // In normal rounds keep special bonus/danger questions for their own rounds where possible.
        $normal = (clone $base);
        if (! $preference) {
            $normal->where('is_danger', false);
        }
        $ids = $normal->pluck('questions.id');
        if ($ids->isEmpty()) {
            $ids = $base->pluck('questions.id');
        }
        if ($ids->count() > 1 && $lastQuestionId) {
            $ids = $ids->reject(fn ($id) => $id === $lastQuestionId)->values();
        }
        if ($ids->isEmpty()) {
            throw new GameException('No unused questions remain for this session’s categories/difficulty. Add questions, widen the filters, or enable question repetition in Settings.', 422);
        }

        return Question::with(['category', 'options'])->findOrFail($ids[random_int(0, $ids->count() - 1)]);
    }

    /** Random question snippets used purely to decorate the slot-machine reel. */
    public function reelDecoys(QuizSession $session, int $count = 24): array
    {
        $rows = $this->baseQuery($session, false)->with('category')
            ->inRandomOrder()->limit($count)->get();
        if ($rows->count() < 6) {
            $rows = Question::active()->with('category')->inRandomOrder()->limit($count)->get();
        }

        return $rows->map(fn ($q) => [
            'text' => \Illuminate\Support\Str::limit($q->question_text, 90),
            'category' => $q->category?->name,
            'color' => $q->category?->color,
        ])->values()->all();
    }
}
