<?php

namespace Database\Seeders;

use App\Models\Participant;
use App\Models\QuestionCategory;
use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CategorySeeder::class, QuestionSeeder::class, ParticipantSeeder::class, ReactionSeeder::class]);

        // Demo administrator for local / workshop machines only. In production create the
        // administrator with:  php artisan quiz:create-admin
        $admin = null;
        if (! app()->isProduction()) {
            $admin = User::firstOrCreate(['email' => env('SEED_ADMIN_EMAIL', 'victor@tradesmartz.com')], [
                'name' => 'Quiz Administrator',
                'password' => env('SEED_ADMIN_PASSWORD', 'Quiz@2026'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ]);
        }

        if (! QuizSession::exists()) {
            $session = new QuizSession([
                'title' => 'Operations Quiz – Sample Session',
                'workshop_title' => 'Operations Excellence Workshop',
                'session_date' => now(),
                'department' => 'Operations',
                'selection_mode' => 'fair_rotation',
                'total_rounds' => 15,
            ]);
            $session->facilitator_id = $admin?->id;
            $session->status = 'ready';
            $session->settings = [];
            $session->save();
            foreach (Participant::active()->pluck('id') as $pid) {
                $session->entries()->create(['participant_id' => $pid]);
            }
            $session->categories()->sync(QuestionCategory::pluck('id'));
        }
    }
}
