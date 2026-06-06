<?php
namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'store_id'    => Store::factory(),
            'category_id' => ProductCategory::factory(),
            'name'        => $this->faker->randomElement([
                'X-Burguer', 'X-Bacon', 'X-Tudo', 'Double Smash',
                'Onion Rings', 'Batata Frita', 'Coca-Cola 350ml', 'Suco de Laranja',
            ]),
            'description' => $this->faker->optional()->sentence(),
            'price'       => $this->faker->randomFloat(2, 15, 80),
            'image_url'   => $this->faker->optional()->imageUrl(400, 400, 'food'),
            'stock'       => $this->faker->optional()->numberBetween(0, 100),
            'is_active'   => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function unlimited(): static
    {
        return $this->state(['stock' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
