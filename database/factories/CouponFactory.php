<?php

namespace Database\Factories;

use App\Enums\CouponDiscountType;
use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('??##??')),
            'discount_type' => $this->faker->randomElement(CouponDiscountType::cases())->value,
            'discount_value' => $this->faker->randomFloat(2, 5, 30),
            'min_order_value' => $this->faker->randomFloat(2, 0, 40),
            'max_uses' => $this->faker->optional()->numberBetween(50, 500),
            'current_uses' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => $this->faker->optional()->dateTimeBetween('+7 days', '+90 days'),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
            'is_active' => false,
        ]);
    }

    public function freeDelivery(): static
    {
        return $this->state([
            'discount_type' => CouponDiscountType::FreeDelivery->value,
            'discount_value' => 0,
        ]);
    }
}
