<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Admin;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'status' => ProjectStatus::Active,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'paid_amount' => 0,
            'deadline' => fake()->date(),
            'repo_url' => null,
            'created_by' => Admin::factory(),
        ];
    }
}
