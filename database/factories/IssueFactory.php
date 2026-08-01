<?php

namespace Database\Factories;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Admin;
use App\Models\Issue;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
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
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'severity' => IssueSeverity::Normal,
            'status' => IssueStatus::Open,
            'created_by' => Admin::factory(),
            'resolved_at' => null,
        ];
    }
}
