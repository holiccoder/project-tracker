<?php

namespace Database\Factories;

use App\Models\DevLog;
use App\Models\Project;
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
            'date' => fake()->date(),
            'content' => fake()->paragraph(),
            'status' => 'in_progress',
            'category' => 'agent_independent',
        ];
    }
}
