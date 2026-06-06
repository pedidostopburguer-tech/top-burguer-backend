<?php
namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'store_id'   => Store::factory(),
            'name'       => $this->faker->randomElement([
                'Hambúrgueres', 'Porções', 'Bebidas', 'Sobremesas',
                'Combos', 'Vegetariano', 'Frango', 'Acompanhamentos',
            ]),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active'  => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
