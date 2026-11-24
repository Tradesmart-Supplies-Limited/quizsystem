<?php

namespace Database\Factories;

use App\Models\QuestionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_category_id' => QuestionCategory::factory(),
            'type' => 'short_answer',
            'difficulty' => 'medium',
            'question_text' => $this->faker->unique()->sentence(8).'?',
            'correct_answer' => $this->faker->words(3, true),
            'explanation' => $this->faker->sentence(),
            'points' => 10,
            'time_limit' => null,
            'is_bonus' => false,
            'is_danger' => false,
            'is_active' => true,
        ];
    }
}
