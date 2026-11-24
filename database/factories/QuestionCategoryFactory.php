<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'color' => $this->faker->hexColor(),
            'icon' => 'tag',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
