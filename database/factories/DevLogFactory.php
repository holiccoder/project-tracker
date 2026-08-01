<?php

namespace Database\Factories;

use App\Models\DevLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevLog>
 */
class DevLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'task_id' => Task::factory(),
            'date' => fake()->date(),
            'content' => fake()->paragraph(),
            'hours_spent' => fake()->randomFloat(1, 0.5, 8),
        ];
    }
}
