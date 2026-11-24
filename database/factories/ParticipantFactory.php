<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ParticipantFactory extends Factory
{
    public function definition(): array
    {
        $first = $this->faker->firstName();

        return [
            'full_name' => $first.' '.$this->faker->lastName(),
            'display_name' => $first,
            'department' => 'Operations',
            'job_title' => $this->faker->jobTitle(),
            'is_active' => true,
        ];
    }
}
