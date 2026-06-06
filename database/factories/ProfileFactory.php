<?php
namespace Database\Factories;

use App\Models\Profile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'id'       => (string) Str::uuid(),
            'user_id'  => User::factory(),
            'store_id' => Store::factory(),
            'role'     => $this->faker->randomElement(Profile::ROLES),
            'is_active' => true,
        ];
    }

    public function storeOwner(): static
    {
        return $this->state(['role' => 'store_owner']);
    }

    public function platformAdmin(): static
    {
        return $this->state(['role' => 'super_admin', 'store_id' => null]);
    }
}
