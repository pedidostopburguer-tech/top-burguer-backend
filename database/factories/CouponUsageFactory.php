<?php
namespace Database\Factories;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponUsageFactory extends Factory
{
    protected $model = CouponUsage::class;

    public function definition(): array
    {
        $store = Store::factory()->create();
        return [
            'store_id'       => $store->id,
            'coupon_id'      => Coupon::factory()->state(['store_id' => $store->id]),
            'order_id'       => Order::factory()->state(['store_id' => $store->id]),
            'customer_phone' => $this->faker->numerify('119########'),
            'used_at'        => now(),
        ];
    }
}
