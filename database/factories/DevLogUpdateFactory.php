<?php

namespace Database\Factories;

use App\Models\DevLog;
use App\Models\DevLogUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DevLogUpdate>
 */
class DevLogUpdateFactory extends Factory
{
    protected $model = DevLogUpdate::class;

    public function definition(): array
    {
        return [
            'dev_log_id' => DevLog::factory(),
            'update' => fake()->sentence(),
        ];
    }
}
