<?php
namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreStatusFactory extends Factory
{
    protected $model = StoreStatus::class;

    public function definition(): array
    {
        return [
            'store_id'   => Store::factory(),
            'is_open'    => $this->faker->boolean(70), // 70% chance aberta
            'updated_at' => now(),
        ];
    }

    public function open(): static
    {
        return $this->state(['is_open' => true]);
    }

    public function closed(): static
    {
        return $this->state(['is_open' => false]);
    }
}
