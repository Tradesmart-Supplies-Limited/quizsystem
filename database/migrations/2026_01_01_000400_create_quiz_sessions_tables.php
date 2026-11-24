<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('workshop_title', 150)->nullable();
            $table->date('session_date')->nullable();
            $table->foreignId('facilitator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('department', 100)->nullable();
            $table->string('status', 15)->default('draft')->index();
            $table->string('selection_mode', 20)->default('fair_rotation');
            $table->unsignedSmallInteger('total_rounds')->default(20);
            $table->unsignedSmallInteger('current_round')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            // Session configuration (timer, scoring, special modes, display, audio overrides).
            // Stored as JSON because it is a cohesive, versionable configuration blob that is
            // always read/written as a whole and never queried column-by-column.
            $table->json('settings')->nullable();
            // Transient play-state (hot seat, speed round countdown, pending mode) – also a blob.
            $table->json('state')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_session_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_category_id')->constrained('question_categories')->cascadeOnDelete();
            $table->unique(['quiz_session_id', 'question_category_id'], 'qsc_unique');
        });

        // Optional fixed question pool for a session (if empty, all active questions in the
        // selected categories are eligible).
        Schema::create('quiz_session_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unique(['quiz_session_id', 'question_id'], 'qsq_unique');
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 9)->default('#4EAADF');
            $table->integer('bonus_points')->default(0)->comment('points awarded to the team directly');
            $table->timestamps();
            $table->unique(['quiz_session_id', 'name']);
        });

        // One row per participant per session: membership, exclusions AND per-session score
        // aggregates (replaces separate participant_scores table – single source, updated in
        // transactions together with quiz_answers).
        Schema::create('quiz_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->integer('score')->default(0);
            $table->unsignedSmallInteger('answered')->default(0);
            $table->unsignedSmallInteger('correct')->default(0);
            $table->unsignedSmallInteger('incorrect')->default(0);
            $table->unsignedSmallInteger('partial')->default(0);
            $table->unsignedSmallInteger('passed')->default(0);
            $table->unsignedSmallInteger('timed_out')->default(0);
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('best_streak')->default(0);
            $table->unsignedSmallInteger('times_selected')->default(0);
            $table->unsignedSmallInteger('last_selected_round')->nullable();
            $table->timestamps();
            $table->unique(['quiz_session_id', 'participant_id'], 'qsp_unique');
            $table->index(['quiz_session_id', 'score']);
        });

        Schema::create('quiz_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('round_type', 20)->default('normal');
            $table->string('mystery_effect', 30)->nullable();
            $table->string('challenge_text', 500)->nullable();
            $table->decimal('multiplier', 4, 2)->default(1);
            $table->integer('points_available')->default(10);
            $table->unsignedSmallInteger('timer_seconds')->nullable();
            $table->boolean('double_or_nothing')->default(false);
            $table->unsignedTinyInteger('hot_seat_index')->nullable();
            $table->string('selection_mode', 20)->nullable();
            $table->string('status', 15)->default('revealed')->index();
            $table->string('result', 15)->nullable();
            $table->integer('points_awarded')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['quiz_session_id', 'round_number']);
            $table->index(['quiz_session_id', 'question_id']);
        });

        // Scoring ledger. Every scoring action is a row; undo flags the row and restores the
        // snapshot so the history stays auditable.
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('result', 15);
            $table->integer('base_points')->default(0);
            $table->integer('streak_bonus')->default(0);
            $table->integer('time_bonus')->default(0);
            $table->integer('extra_bonus')->default(0);
            $table->integer('points')->default(0);
            $table->boolean('is_override')->default(false);
            $table->unsignedSmallInteger('seconds_remaining')->nullable();
            $table->json('snapshot')->nullable()->comment('participant aggregates before this answer, for exact undo');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('undone_at')->nullable()->index();
            $table->timestamps();
            $table->index(['quiz_session_id', 'undone_at']);
        });

        Schema::create('session_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->index();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_events');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_rounds');
        Schema::dropIfExists('quiz_session_participants');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('quiz_session_questions');
        Schema::dropIfExists('quiz_session_categories');
        Schema::dropIfExists('quiz_sessions');
    }
};
