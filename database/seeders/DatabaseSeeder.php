<?php
namespace Database\Seeders;
use App\Models\{Store, StoreSettings, StoreStatus};
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::create([
            'name'      => 'Top Burguer',
            'slug'      => 'top-burguer',
            'is_active' => true,
        ]);

        StoreSettings::create([
            'store_id'   => $store->id,
            'store_name' => 'Top Burguer',
            'slogan'     => 'Os melhores smash burgers da região',
        ]);

        StoreStatus::create([
            'store_id' => $store->id,
            'is_open'  => true,
            'is_auto'  => true,
        ]);

        $this->command->info("Loja '{$store->name}' criada com slug: {$store->slug}");
    }
}
