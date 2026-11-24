<?php

namespace Tests;

use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function facilitator(): User
    {
        return User::factory()->create();
    }

    /** A ready session with N participants and M questions in one category. */
    protected function makeSession(int $people = 4, int $questions = 10, array $settings = [], string $mode = 'random', int $rounds = 50): QuizSession
    {
        $cat = QuestionCategory::factory()->create();
        Question::factory()->count($questions)->create(['question_category_id' => $cat->id]);
        $s = new QuizSession(['title' => 'Test', 'selection_mode' => $mode, 'total_rounds' => $rounds]);
        // Deterministic defaults for tests: no random special rounds unless asked for.
        $s->settings = array_replace_recursive([
            'modes' => ['bonus_enabled' => false, 'danger_enabled' => false, 'mystery_enabled' => false],
        ], $settings);
        $s->status = 'ready';
        $s->save();
        foreach (Participant::factory()->count($people)->create() as $p) {
            $s->entries()->create(['participant_id' => $p->id]);
        }

        return $s->refresh();
    }
}
