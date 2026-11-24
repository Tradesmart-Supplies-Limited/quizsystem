<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_every_admin_page_renders_with_seeded_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertGreaterThanOrEqual(50, Question::count());
        $this->assertGreaterThanOrEqual(15, Participant::count());
        $this->assertGreaterThanOrEqual(5, QuestionCategory::count());
        $this->assertTrue(Question::where('is_bonus', true)->exists());
        $this->assertTrue(Question::where('is_danger', true)->exists());
        $this->assertTrue(Question::where('type', 'team')->exists());

        $admin = User::where('role', 'admin')->first();
        $this->actingAs($admin);
        $session = \App\Models\QuizSession::first();
        foreach ([
            route('admin.dashboard'), route('admin.questions.index'), route('admin.questions.create'), route('admin.questions.edit', Question::first()),
            route('admin.categories.index'), route('admin.categories.create'), route('admin.categories.edit', QuestionCategory::first()),
            route('admin.participants.index'), route('admin.participants.create'), route('admin.participants.show', Participant::first()), route('admin.participants.edit', Participant::first()),
            route('admin.sessions.index'), route('admin.sessions.create'), route('admin.sessions.show', $session), route('admin.sessions.edit', $session),
            route('admin.reports.index'), route('admin.reactions.index'), route('admin.settings.edit'), route('admin.users.index'), route('admin.users.create'),
            route('play', $session),
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson(route('play.state', $session))->assertOk()->assertJsonStructure(['session', 'config', 'participants', 'leaderboard', 'reactions', 'audio' => ['events', 'catalogue']]);
        $this->get(route('admin.reports.participants'))->assertOk();
        $this->get(route('admin.reports.questions'))->assertOk();
    }

    public function test_game_screen_does_not_expose_admin_navigation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::first());
        $this->get(route('play', \App\Models\QuizSession::first()))->assertOk()
            ->assertDontSee('admin-sidebar')->assertSee('SPIN THE WHEEL')->assertSee('Question for');
    }

    public function test_admin_default_settings_and_sound_mapping_save(): void
    {
        $this->actingAs($this->admin())->put(route('admin.settings.update'), [
            'settings' => ['timer' => ['default_seconds' => 45, 'enabled' => 1]],
            'events' => ['correct' => ['applause', 'not_a_sound']],
            'disabled' => ['laugh'],
        ])->assertSessionHas('success');
        $this->assertSame(45, \App\Support\GameDefaults::session()['timer']['default_seconds']);
        $audio = \App\Support\GameDefaults::audioConfig();
        $this->assertSame(['applause'], $audio['events']['correct']);
        $this->assertSame(['laugh'], $audio['disabled']);
    }

    public function test_sound_files_exist_for_every_catalogue_entry_and_theme(): void
    {
        foreach (array_keys(\App\Support\GameDefaults::soundThemes()) as $theme) {
            foreach (array_keys(\App\Support\GameDefaults::sounds()) as $key) {
                $this->assertFileExists(public_path("sounds/{$theme}/{$key}.mp3"));
            }
        }
    }
}
