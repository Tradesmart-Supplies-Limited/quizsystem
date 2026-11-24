<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Services\GameEngine;
use App\Services\ScoringService;
use Tests\TestCase;

class SessionTest extends TestCase
{
    public function test_session_can_be_created_from_the_form(): void
    {
        $people = Participant::factory()->count(3)->create();
        $cat = QuestionCategory::factory()->create();
        $this->actingAs($this->facilitator())->post(route('admin.sessions.store'), [
            'title' => 'Workshop quiz', 'workshop_title' => 'Safety', 'session_date' => '2026-11-24', 'selection_mode' => 'fair_rotation',
            'total_rounds' => 12, 'participant_ids' => $people->pluck('id')->all(), 'category_ids' => [$cat->id],
            'settings' => ['timer' => ['default_seconds' => 20, 'enabled' => 1], 'scoring' => ['streak_bonus' => 7], 'modes' => ['difficulties' => ['', 'easy']], 'hacker' => ['x' => 1]],
            'after' => 'play',
        ])->assertRedirect();
        $s = QuizSession::firstOrFail();
        $this->assertSame('ready', $s->status);
        $this->assertSame(3, $s->entries()->count());
        $this->assertSame([$cat->id], $s->categories()->pluck('question_categories.id')->all());
        $this->assertSame(20, $s->config('timer.default_seconds'));
        $this->assertFalse($s->config('timer.ticking'));           // unticked checkbox = false
        $this->assertSame(7, $s->config('scoring.streak_bonus'));
        $this->assertSame(['easy'], $s->config('modes.difficulties'));
        $this->assertArrayNotHasKey('hacker', $s->settings);        // unknown keys stripped
    }

    public function test_state_endpoint_resumes_open_round_after_refresh(): void
    {
        $s = $this->makeSession(3, 10);
        $this->actingAs($this->facilitator());
        $round = $this->postJson(route('play.spin', $s), ['round_number' => 1])->json('round');
        $state = $this->getJson(route('play.state', $s))->assertOk();
        $this->assertSame($round['id'], $state->json('round.id'));
        $this->assertSame('revealed', $state->json('round.status'));
        $this->assertSame($round['question']['id'], $state->json('round.question.id'));
        $this->assertSame(1, $state->json('session.current_round'));
    }

    public function test_in_game_settings_do_not_reset_scores_or_current_round(): void
    {
        $s = $this->makeSession(2, 10);
        app(GameEngine::class)->spin($s); app(ScoringService::class)->score($s, 'correct');
        $open = app(GameEngine::class)->spin($s);
        $this->actingAs($this->facilitator())->postJson(route('play.settings', $s), [
            'settings' => ['timer' => ['default_seconds' => 15], 'audio' => ['enabled' => false]], 'title' => 'Renamed', 'total_rounds' => 1,
        ])->assertOk()->assertJsonPath('config.timer.default_seconds', 15);
        $s->refresh();
        $this->assertSame(10, (int) $s->entries()->sum('score'));
        $this->assertSame('revealed', $open->fresh()->status);
        $this->assertSame(2, $s->total_rounds); // cannot drop below rounds already played
        $this->assertSame('Renamed', $s->title);
    }

    public function test_timer_settings_are_sanitised(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->facilitator())->postJson(route('play.settings', $s), ['settings' => ['timer' => ['default_seconds' => 99999, 'enabled' => 'false']]])->assertOk();
        $this->assertSame(600, $s->fresh()->config('timer.default_seconds'));
        $this->assertFalse($s->fresh()->config('timer.enabled'));
        $r = app(GameEngine::class)->spin($s->fresh());
        $this->assertNull($r->timer_seconds); // timer disabled
    }

    public function test_pause_resume_complete_and_final(): void
    {
        $s = $this->makeSession(3, 10);
        $this->actingAs($this->facilitator());
        $this->postJson(route('play.status', $s), ['action' => 'start'])->assertOk();
        $this->postJson(route('play.status', $s), ['action' => 'pause'])->assertJsonPath('session.status', 'paused');
        $this->postJson(route('play.spin', $s), ['round_number' => 1])->assertStatus(409);
        $this->postJson(route('play.status', $s), ['action' => 'resume'])->assertJsonPath('session.status', 'active');
        $this->postJson(route('play.spin', $s), ['round_number' => 1])->assertOk();
        $this->postJson(route('play.score', $s), ['result' => 'correct'])->assertOk();
        $this->postJson(route('play.spin', $s), ['round_number' => 2])->assertOk(); // left open
        $final = $this->getJson(route('play.final', $s))->assertOk();
        $this->assertSame(10, $final->json('leaderboard.0.score'));
        $this->postJson(route('play.status', $s), ['action' => 'complete'])->assertJsonPath('session.status', 'completed');
        $this->assertSame(1, $s->rounds()->count()); // unscored round discarded
        $this->assertNotNull($s->fresh()->ended_at);
        $this->postJson(route('play.score', $s), ['result' => 'correct'])->assertStatus(409);
    }

    public function test_results_pages_exports_and_duplicate(): void
    {
        $s = $this->makeSession(3, 10);
        app(GameEngine::class)->spin($s); app(ScoringService::class)->score($s, 'correct');
        $this->actingAs($this->facilitator());
        $this->get(route('admin.sessions.show', $s))->assertOk()->assertSee('Leaderboard');
        $this->get(route('admin.sessions.report', $s))->assertOk()->assertSee('Session Report');
        $this->assertStringContainsString('LEADERBOARD', $this->get(route('admin.sessions.export', $s))->streamedContent());
        $this->post(route('admin.sessions.duplicate', $s))->assertRedirect();
        $this->assertSame(2, QuizSession::count());
        $this->assertSame(3, QuizSession::latest('id')->first()->entries()->count());
    }

    public function test_only_admins_can_delete_sessions(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->facilitator())->delete(route('admin.sessions.destroy', $s))->assertForbidden();
        $this->actingAs($this->admin())->delete(route('admin.sessions.destroy', $s))->assertRedirect();
        $this->assertSoftDeleted($s);
    }

    public function test_in_game_participant_management(): void
    {
        $s = $this->makeSession(2, 5);
        $this->actingAs($this->facilitator());
        $this->postJson(route('play.participants.add', $s), ['full_name' => 'New Person'])->assertOk()->assertJsonCount(3, 'participants');
        $pid = Participant::where('full_name', 'New Person')->value('id');
        $this->patchJson(route('play.participants.update', [$s, $pid]), ['is_excluded' => true, 'display_name' => 'Newbie'])
            ->assertOk()->assertJsonFragment(['name' => 'Newbie', 'excluded' => true]);
        $this->deleteJson(route('play.participants.remove', [$s, $pid]))->assertOk()->assertJsonCount(2, 'participants');
    }
}
