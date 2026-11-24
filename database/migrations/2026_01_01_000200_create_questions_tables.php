<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_category_id')->constrained('question_categories')->restrictOnDelete();
            $table->string('type', 30)->default('multiple_choice')->index();
            $table->string('difficulty', 10)->default('medium')->index();
            $table->text('question_text');
            $table->text('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(10);
            $table->unsignedSmallInteger('time_limit')->nullable()->comment('seconds; null = session default');
            $table->boolean('is_bonus')->default(false)->index();
            $table->boolean('is_danger')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['is_active', 'question_category_id', 'difficulty']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('label', 2);
            $table->string('option_text', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['question_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
