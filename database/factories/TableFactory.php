<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\Store;
use App\Models\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'number' => (string) $this->faker->unique()->numberBetween(1, 999),
            'qr_token' => Str::random(12),
            'capacity' => $this->faker->numberBetween(2, 8),
            'status' => TableStatus::Livre,
            'is_active' => true,
        ];
    }

    public function ocupada(): static
    {
        return $this->state(fn () => ['status' => TableStatus::Ocupada]);
    }

    public function limpeza(): static
    {
        return $this->state(fn () => ['status' => TableStatus::Limpeza]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
