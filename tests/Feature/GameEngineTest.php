<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Models\Team;
use App\Services\GameEngine;
use App\Services\ScoringService;
use Tests\TestCase;

class GameEngineTest extends TestCase
{
    private function engine(): GameEngine { return app(GameEngine::class); }
    private function scoring(): ScoringService { return app(ScoringService::class); }

    private function playRound(QuizSession $s, string $result = 'correct'): \App\Models\QuizRound
    {
        $r = $this->engine()->spin($s);
        $this->scoring()->score($s, $result);

        return $r;
    }

    public function test_spin_endpoint_returns_selection_that_is_recorded(): void
    {
        $s = $this->makeSession();
        $res = $this->actingAs($this->facilitator())->postJson(route('play.spin', $s), ['round_number' => 1])->assertOk();
        $round = $s->rounds()->first();
        $this->assertSame($round->participant_id, $res->json('round.participant.id'));
        $this->assertSame($round->question_id, $res->json('round.question.id'));
        $this->assertContains($round->participant_id, collect($res->json('wheel'))->pluck('id')->all()); // the wheel contains the winner
        $this->assertNotEmpty($res->json('reel'));
        $this->assertSame('active', $s->fresh()->status);
        $this->assertSame(1, $s->fresh()->current_round);
    }

    public function test_duplicate_spin_requests_are_idempotent_and_open_rounds_block_new_spins(): void
    {
        $s = $this->makeSession();
        $this->actingAs($this->facilitator());
        $a = $this->postJson(route('play.spin', $s), ['round_number' => 1])->json('round.id');
        $b = $this->postJson(route('play.spin', $s), ['round_number' => 1])->json('round.id');
        $this->assertSame($a, $b);
        $this->assertSame(1, $s->rounds()->count());
        $this->postJson(route('play.spin', $s), ['round_number' => 2])->assertStatus(409);
    }

    public function test_only_active_questions_and_selected_categories_are_used_and_never_repeat(): void
    {
        $s = $this->makeSession(3, 6);
        $other = QuestionCategory::factory()->create();
        $foreign = Question::factory()->count(5)->create(['question_category_id' => $other->id]);
        $inactive = Question::factory()->create(['question_category_id' => $s->categories()->first()?->id ?? Question::first()->question_category_id, 'is_active' => false]);
        $s->categories()->sync([Question::first()->question_category_id]);

        $used = [];
        for ($i = 0; $i < 6; $i++) {
            $used[] = $this->playRound($s)->question_id;
        }
        $this->assertCount(6, array_unique($used));
        $this->assertNotContains($inactive->id, $used);
        $this->assertEmpty(array_intersect($foreign->pluck('id')->all(), $used));
        // pool exhausted
        $this->expectExceptionMessage('No unused questions remain');
        $this->engine()->spin($s);
    }

    public function test_question_repetition_can_be_enabled(): void
    {
        $s = $this->makeSession(2, 1, ['modes' => ['allow_repeat_questions' => true]]);
        $this->playRound($s);
        $this->playRound($s);
        $this->assertSame(2, $s->rounds()->count());
        $this->assertSame(2, Question::first()->times_used);
    }

    public function test_difficulty_filter_is_respected(): void
    {
        $s = $this->makeSession(2, 0, ['modes' => ['difficulties' => ['hard']]]);
        $cat = QuestionCategory::factory()->create();
        Question::factory()->count(3)->create(['question_category_id' => $cat->id, 'difficulty' => 'easy']);
        $hard = Question::factory()->create(['question_category_id' => $cat->id, 'difficulty' => 'hard']);
        $this->assertSame($hard->id, $this->playRound($s)->question_id);
    }

    public function test_excluded_and_inactive_participants_are_never_selected(): void
    {
        $s = $this->makeSession(4, 30);
        $entries = $s->entries()->get();
        $entries[0]->update(['is_excluded' => true]);
        $entries[1]->participant->update(['is_active' => false]);
        $blocked = [$entries[0]->participant_id, $entries[1]->participant_id];
        for ($i = 0; $i < 12; $i++) {
            $this->assertNotContains($this->playRound($s)->participant_id, $blocked);
        }
    }

    public function test_random_among_remaining_selects_everyone_before_repeating(): void
    {
        $s = $this->makeSession(5, 30, [], 'remaining');
        $first = [];
        for ($i = 0; $i < 5; $i++) {
            $first[] = $this->playRound($s, 'passed')->participant_id;
        }
        $this->assertCount(5, array_unique($first));
        $second = [];
        for ($i = 0; $i < 5; $i++) {
            $second[] = $this->playRound($s)->participant_id;
        }
        $this->assertCount(5, array_unique($second));
    }

    public function test_fair_rotation_prioritises_people_who_have_not_answered(): void
    {
        $s = $this->makeSession(4, 30, [], 'fair_rotation');
        $seen = [];
        for ($i = 0; $i < 4; $i++) {
            $seen[] = $this->playRound($s)->participant_id;
        }
        $this->assertCount(4, array_unique($seen)); // full cycle
        $next = $this->playRound($s)->participant_id;  // cycle resets
        $this->assertNotSame(end($seen), $next);       // no back-to-back repeat
    }

    public function test_repeat_selection_toggle(): void
    {
        $s = $this->makeSession(2, 30);
        $prev = null;
        for ($i = 0; $i < 10; $i++) {
            $p = $this->playRound($s)->participant_id;
            $this->assertNotSame($prev, $p);
            $prev = $p;
        }
    }

    public function test_facilitator_selection_mode(): void
    {
        $s = $this->makeSession(3, 10, [], 'facilitator');
        $this->actingAs($this->facilitator());
        $this->postJson(route('play.spin', $s), ['round_number' => 1])->assertStatus(422);
        $pid = $s->entries()->first()->participant_id;
        $this->postJson(route('play.spin', $s), ['round_number' => 1, 'participant_id' => $pid])->assertOk()->assertJsonPath('round.participant.id', $pid);
    }

    public function test_team_selection_picks_team_members(): void
    {
        $s = $this->makeSession(6, 20, ['modes' => ['team_mode' => true]], 'team');
        $this->actingAs($this->facilitator())->postJson(route('play.teams.generate', $s), ['count' => 2])->assertOk();
        $this->assertSame(2, Team::where('quiz_session_id', $s->id)->count());
        $this->assertSame(0, $s->entries()->whereNull('team_id')->count());
        $r = $this->playRound($s);
        $this->assertNotNull($r->team_id);
        $this->assertSame($r->team_id, $s->entries()->where('participant_id', $r->participant_id)->value('team_id'));
    }

    public function test_hot_seat_gives_the_same_person_several_questions(): void
    {
        $s = $this->makeSession(4, 20, ['modes' => ['hot_seat_questions' => 3]]);
        $this->engine()->queueMode($s, 'hot_seat');
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $this->playRound($s);
            $this->assertSame('hot_seat', $r->round_type);
            $ids[] = $r->participant_id;
        }
        $this->assertCount(1, array_unique($ids));
        $this->assertSame('normal', $this->playRound($s)->round_type);
    }

    public function test_bonus_danger_and_speed_rounds(): void
    {
        $s = $this->makeSession(3, 20, ['scoring' => ['bonus_multiplier' => 3], 'modes' => ['speed_timer' => 12, 'speed_rounds' => 2]]);
        $this->engine()->queueMode($s, 'bonus');
        $r = $this->playRound($s);
        $this->assertSame('bonus', $r->round_type);
        $this->assertSame(30, $r->points_available);

        $this->engine()->queueMode($s, 'danger');
        $this->assertSame('danger', $this->playRound($s, 'incorrect')->round_type);

        $this->engine()->queueMode($s, 'speed');
        $r = $this->playRound($s);
        $this->assertSame('speed', $r->round_type);
        $this->assertSame(12, $r->timer_seconds);
    }

    public function test_mystery_round_records_an_effect(): void
    {
        $s = $this->makeSession(3, 20);
        $this->engine()->queueMode($s, 'mystery');
        $r = $this->engine()->spin($s);
        $this->assertSame('mystery', $r->round_type);
        $this->assertArrayHasKey($r->mystery_effect, GameEngine::MYSTERY_EFFECTS);
        if ($r->mystery_effect === 'funny_challenge') {
            $this->assertNull($r->question_id);
            $this->assertNotEmpty($r->challenge_text);
        }
    }

    public function test_timer_uses_question_limit_then_session_default(): void
    {
        $s = $this->makeSession(2, 0, ['timer' => ['default_seconds' => 45]]);
        $cat = QuestionCategory::factory()->create();
        Question::factory()->create(['question_category_id' => $cat->id, 'time_limit' => 20]);
        $r = $this->playRound($s);
        $this->assertSame(20, $r->timer_seconds);
        Question::factory()->create(['question_category_id' => $cat->id, 'time_limit' => null]);
        $this->assertSame(45, $this->playRound($s)->timer_seconds);
    }

    public function test_void_returns_round_and_question(): void
    {
        $s = $this->makeSession(2, 5);
        $r = $this->engine()->spin($s);
        $this->engine()->void($s);
        $this->assertSame(0, $s->fresh()->current_round);
        $this->assertSame(0, Question::find($r->question_id)->times_used);
        $this->assertSame(0, $s->rounds()->count());
    }

    public function test_cannot_play_beyond_total_rounds_or_when_paused(): void
    {
        $s = $this->makeSession(2, 10, [], 'random', 1);
        $this->playRound($s);
        try {
            $this->engine()->spin($s);
            $this->fail('expected exception');
        } catch (\App\Exceptions\GameException $e) {
            $this->assertStringContainsString('All rounds', $e->getMessage());
        }
        $s2 = $this->makeSession(2, 5);
        $this->engine()->setStatus($s2, 'start');
        $this->engine()->setStatus($s2, 'pause');
        $this->expectException(\App\Exceptions\GameException::class);
        $this->engine()->spin($s2);
    }
}
