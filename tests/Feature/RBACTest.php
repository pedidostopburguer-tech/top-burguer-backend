<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RBACTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role, ?Store $store = null): User
    {
        $store ??= Store::factory()->create();
        $user = User::factory()->create(['password' => Hash::make('senha123456')]);

        Profile::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // auth:sanctum protege rotas admin
    // -------------------------------------------------------------------------

    public function test_store_owner_autenticado_acessa_rota_admin(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        Sanctum::actingAs($user);

        $this->withHeader('X-Store-Slug', $store->slug)
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(200);
    }

    public function test_unauthenticated_nao_acessa_rota_admin(): void
    {
        $store = Store::factory()->create();

        $this->withHeader('X-Store-Slug', $store->slug)
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Profile helpers
    // -------------------------------------------------------------------------

    public function test_profile_is_platform_role(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'store_id' => null,
            'role' => 'super_admin',
        ]);

        $this->assertTrue($profile->isPlatformRole());
        $this->assertFalse($profile->isStoreRole());
    }

    public function test_profile_is_store_role(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create();
        $profile = Profile::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'role' => 'kitchen_staff',
        ]);

        $this->assertFalse($profile->isPlatformRole());
        $this->assertTrue($profile->isStoreRole());
    }
}
