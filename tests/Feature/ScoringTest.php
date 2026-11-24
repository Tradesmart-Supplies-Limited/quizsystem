<?php

namespace Tests\Feature;

use App\Models\QuizSession;
use App\Services\GameEngine;
use App\Services\ScoringService;
use Tests\TestCase;

class ScoringTest extends TestCase
{
    private function entry(QuizSession $s, int $pid) { return $s->entries()->where('participant_id', $pid)->first(); }

    public function test_score_endpoint_updates_scores_leaderboard_and_history(): void
    {
        $s = $this->makeSession(3, 10);
        $this->actingAs($this->facilitator());
        $round = $this->postJson(route('play.spin', $s), ['round_number' => 1])->json('round');
        $res = $this->postJson(route('play.score', $s), ['result' => 'correct', 'round_id' => $round['id']])->assertOk();
        $this->assertSame(10, $res->json('answer.points'));
        $this->assertSame($round['participant']['id'], $res->json('leaderboard.0.id'));
        $this->assertSame(10, $res->json('leaderboard.0.score'));
        $this->assertSame('correct', $res->json('history.0.result'));
        // double scoring the same round is refused
        $this->postJson(route('play.score', $s), ['result' => 'correct', 'round_id' => $round['id']])->assertStatus(409);
    }

    public function test_results_partial_pass_and_override(): void
    {
        $s = $this->makeSession(1, 10, ['scoring' => ['partial_percent' => 50]]);
        $e = app(GameEngine::class); $sc = app(ScoringService::class);
        $r = $e->spin($s); $this->assertSame(5, $sc->score($s, 'partial')->points);
        $e->spin($s); $this->assertSame(0, $sc->score($s, 'passed')->points);
        $e->spin($s); $this->assertSame(-3, $sc->score($s, 'incorrect', ['override_points' => -3])->points);
        $entry = $this->entry($s, $r->participant_id);
        $this->assertSame(2, $entry->score);
        $this->assertSame(3, $entry->answered);
        $this->assertSame(1, $entry->partial);
        $this->assertSame(1, $entry->passed);
        $this->assertSame(1, $entry->incorrect);
    }

    public function test_streak_bonus_and_star_bonus(): void
    {
        $s = $this->makeSession(1, 10, ['scoring' => ['streak_threshold' => 3, 'streak_bonus' => 5, 'star_bonus_points' => 4]]);
        $e = app(GameEngine::class); $sc = app(ScoringService::class);
        $points = [];
        for ($i = 0; $i < 3; $i++) { $e->spin($s); $points[] = $sc->score($s, 'correct')->points; }
        $this->assertSame([10, 10, 15], $points);
        $e->spin($s); $this->assertSame(19, $sc->score($s, 'correct', ['star' => true])->points);
        $e->spin($s); $sc->score($s, 'incorrect');
        $this->assertSame(0, $s->entries()->first()->current_streak);
        $this->assertSame(4, $s->entries()->first()->best_streak);
    }

    public function test_danger_round_can_deduct_and_double_or_nothing(): void
    {
        $s = $this->makeSession(1, 10, ['scoring' => ['danger_multiplier' => 2, 'danger_negative' => true, 'danger_penalty_percent' => 50]]);
        $e = app(GameEngine::class); $sc = app(ScoringService::class);
        $e->queueMode($s, 'danger'); $e->spin($s);
        $this->assertSame(-10, $sc->score($s, 'incorrect')->points);     // 20 pts * 50 %

        $e->spin($s); $sc->setDoubleOrNothing($s, true);
        $this->assertSame(20, $sc->score($s, 'correct')->points);
        $e->spin($s); $sc->setDoubleOrNothing($s, true);
        $this->assertSame(-10, $sc->score($s, 'incorrect')->points);
    }

    public function test_time_bonus(): void
    {
        $s = $this->makeSession(1, 10, ['scoring' => ['time_bonus_enabled' => true, 'time_bonus_max' => 6], 'timer' => ['default_seconds' => 30]]);
        app(GameEngine::class)->spin($s);
        $this->assertSame(13, app(ScoringService::class)->score($s, 'correct', ['seconds_remaining' => 15])->points);
    }

    public function test_undo_restores_previous_state_exactly(): void
    {
        $s = $this->makeSession(1, 10, ['scoring' => ['streak_threshold' => 2, 'streak_bonus' => 5]]);
        $e = app(GameEngine::class); $sc = app(ScoringService::class);
        $r1 = $e->spin($s); $sc->score($s, 'correct');
        $before = $this->entry($s, $r1->participant_id)->only(['score', 'answered', 'correct', 'current_streak', 'best_streak']);
        $r2 = $e->spin($s); $sc->score($s, 'correct'); // 15 with streak bonus
        $this->assertSame(25, $this->entry($s, $r1->participant_id)->score);

        $this->actingAs($this->facilitator())->postJson(route('play.undo', $s))->assertOk()->assertJsonPath('round.status', 'revealed');
        $this->assertSame($before, $this->entry($s, $r1->participant_id)->only(array_keys($before)));
        $this->assertSame('revealed', $r2->fresh()->status);
        $this->assertNotNull($s->answers()->whereNotNull('undone_at')->first());

        // re-score after undo works and history stays consistent
        $this->assertSame(10, $sc->score($s, 'partial', ['override_points' => 10])->points);
        $this->assertSame(2, $s->answers()->whereNull('undone_at')->count());
    }

    public function test_team_question_awards_team_points_and_undo_reverses_it(): void
    {
        $s = $this->makeSession(4, 0, ['modes' => ['team_mode' => true], 'scoring' => ['team_question_award' => 'team']]);
        $cat = \App\Models\QuestionCategory::factory()->create();
        \App\Models\Question::factory()->create(['question_category_id' => $cat->id, 'type' => 'team', 'points' => 20]);
        $this->actingAs($this->facilitator())->postJson(route('play.teams.generate', $s), ['count' => 2]);
        $r = app(GameEngine::class)->spin($s);
        app(ScoringService::class)->score($s, 'correct');
        $this->assertSame(20, $r->team->fresh()->bonus_points);
        $this->assertSame(0, $this->entry($s, $r->participant_id)->score);
        $board = app(\App\Services\GameState::class)->teamLeaderboard($s);
        $this->assertSame(20, collect($board)->firstWhere('id', $r->team_id)['score']);
        app(ScoringService::class)->undo($s);
        $this->assertSame(0, $r->team->fresh()->bonus_points);
    }

    public function test_scores_are_isolated_between_sessions(): void
    {
        $a = $this->makeSession(2, 5);
        $b = new QuizSession(['title' => 'B', 'selection_mode' => 'random', 'total_rounds' => 5]);
        $b->status = 'ready'; $b->save();
        foreach ($a->entries as $en) { $b->entries()->create(['participant_id' => $en->participant_id]); }
        app(GameEngine::class)->spin($a); app(ScoringService::class)->score($a, 'correct');
        $this->assertSame(10, (int) $a->entries()->sum('score'));
        $this->assertSame(0, (int) $b->entries()->sum('score'));
    }
}
