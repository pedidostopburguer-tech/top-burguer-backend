<?php
namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'id'        => (string) Str::uuid(),
            'name'      => $name,
            'slug'      => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'logo_url'  => $this->faker->optional()->imageUrl(200, 200, 'food'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
