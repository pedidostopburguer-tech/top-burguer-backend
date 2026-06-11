<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Hambúrgueres', 'Porções', 'Bebidas', 'Sobremesas',
            'Combos', 'Vegetariano', 'Frango', 'Acompanhamentos',
        ]);

        return [
            'store_id' => Store::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
