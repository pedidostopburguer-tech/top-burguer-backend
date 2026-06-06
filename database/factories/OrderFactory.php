<?php
namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal    = $this->faker->randomFloat(2, 20, 200);
        $deliveryFee = $this->faker->randomFloat(2, 0, 15);

        return [
            'store_id'         => Store::factory(),
            'customer_name'    => $this->faker->name(),
            'customer_phone'   => $this->faker->numerify('119########'),
            'customer_address' => $this->faker->address(),
            'items'            => $this->fakeItems(),
            'subtotal'         => $subtotal,
            'delivery_fee'     => $deliveryFee,
            'discount'         => 0.00,
            'total'            => round($subtotal + $deliveryFee, 2),
            'status'           => 'pendente',
            'payment_method'   => $this->faker->randomElement(['dinheiro', 'pix', 'cartao_credito', 'cartao_debito']),
            'notes'            => $this->faker->optional()->sentence(),
            'coupon_code'      => null,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function withCoupon(string $code, float $discount): static
    {
        return $this->state(fn (array $attrs) => [
            'coupon_code' => $code,
            'discount'    => $discount,
            'total'       => round($attrs['subtotal'] + $attrs['delivery_fee'] - $discount, 2),
        ]);
    }

    private function fakeItems(): array
    {
        $count = $this->faker->numberBetween(1, 4);
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $price = $this->faker->randomFloat(2, 15, 60);
            $qty   = $this->faker->numberBetween(1, 3);
            $items[] = [
                'product_id' => (string) \Illuminate\Support\Str::uuid(),
                'name'       => $this->faker->randomElement(['X-Burguer', 'X-Bacon', 'Batata Frita', 'Coca-Cola']),
                'price'      => $price,
                'quantity'   => $qty,
                'subtotal'   => round($price * $qty, 2),
            ];
        }
        return $items;
    }
}
