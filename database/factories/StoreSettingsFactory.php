<?php
namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreSettingsFactory extends Factory
{
    protected $model = StoreSettings::class;

    public function definition(): array
    {
        return [
            'store_id'             => Store::factory(),
            'store_name'           => $this->faker->company(),
            'store_description'    => $this->faker->sentence(),
            'store_address'        => $this->faker->address(),
            'whatsapp_number'      => $this->faker->numerify('5511#########'),
            'maps_url'             => 'https://maps.google.com/?q=' . $this->faker->latitude() . ',' . $this->faker->longitude(),
            'opening_hours'        => [
                ['day' => 'Segunda-feira', 'hours' => '18:00h às 23:00h'],
                ['day' => 'Sexta-feira',   'hours' => '18:00h às 04:00h'],
                ['day' => 'Sábado',        'hours' => '18:00h às 04:00h'],
                ['day' => 'Domingo',       'hours' => '18:00h às 23:00h'],
            ],
            'neighborhood_fees'    => [
                'centro'      => 5.00,
                'laranjeiras' => 6.00,
                'vila nova'   => 8.00,
            ],
            'minimum_order'        => $this->faker->randomFloat(2, 20, 50),
            'default_delivery_fee' => $this->faker->randomFloat(2, 3, 15),
        ];
    }
}
