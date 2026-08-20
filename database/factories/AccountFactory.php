<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'website_name' => fake()->company(),
            'login_url' => fake()->url().'/login',
            'username' => fake()->userName(),
            'password' => fake()->password(12),
            'note' => null,
        ];
    }
}
