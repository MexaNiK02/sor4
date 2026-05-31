<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(10),
            'host_name' => fake()->name(),
            'is_published' => true,
        ];
    }
}
