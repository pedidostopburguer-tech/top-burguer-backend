<?php

namespace Tests\Feature\Table;

use App\Models\Order;
use App\Models\Profile;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableManagementTest extends TestCase
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

    public function test_lista_mesas_da_loja_autenticada(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        Table::factory()->count(3)->create(['store_id' => $store->id]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->getJson('/api/v1/admin/tables');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_cria_mesa_com_qr_token_gerado_automaticamente(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->postJson('/api/v1/admin/tables', [
                'number' => '01',
                'capacity' => 4,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.number', '01')
            ->assertJsonPath('data.status', 'livre')
            ->assertJsonPath('data.is_active', true);

        $this->assertNotEmpty($response->json('data.qr_token'));

        $this->assertDatabaseHas('tables', [
            'store_id' => $store->id,
            'number' => '01',
            'status' => 'livre',
            'is_active' => true,
        ]);
    }

    public function test_rejeita_criacao_de_mesa_com_number_duplicado_na_mesma_loja(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        Table::factory()->create(['store_id' => $store->id, 'number' => '01']);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->postJson('/api/v1/admin/tables', ['number' => '01']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_permite_o_mesmo_number_em_lojas_diferentes(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        Table::factory()->create(['store_id' => $store2->id, 'number' => '01']);

        $user = $this->createUser('store_owner', $store1);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store1->slug)
            ->postJson('/api/v1/admin/tables', ['number' => '01']);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.number', '01');
    }

    public function test_atualiza_number_e_capacity_de_uma_mesa(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id, 'number' => '01', 'capacity' => 2]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->putJson("/api/v1/admin/tables/{$table->id}", [
                'number' => '01-A',
                'capacity' => 6,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.number', '01-A')
            ->assertJsonPath('data.capacity', 6);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'number' => '01-A',
            'capacity' => 6,
        ]);
    }

    public function test_atualiza_status_da_mesa_via_patch_status(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id, 'status' => 'livre']);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/admin/tables/{$table->id}/status", ['status' => 'ocupada']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ocupada');

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'status' => 'ocupada',
        ]);
    }

    public function test_rejeita_status_invalido(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/admin/tables/{$table->id}/status", ['status' => 'fechada']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rotaciona_o_qr_token_e_invalida_o_anterior(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id]);
        $oldToken = $table->qr_token;

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/admin/tables/{$table->id}/rotate-qr");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $newToken = $response->json('data.qr_token');
        $this->assertNotEquals($oldToken, $newToken);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'qr_token' => $newToken,
        ]);
        $this->assertDatabaseMissing('tables', [
            'id' => $table->id,
            'qr_token' => $oldToken,
        ]);
    }

    public function test_bloqueia_exclusao_de_mesa_com_pedido_em_aberto(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id, 'number' => '05']);

        Order::factory()->create([
            'store_id' => $store->id,
            'channel' => 'mesa',
            'table_number' => '05',
            'status' => 'Em produção',
        ]);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->deleteJson("/api/v1/admin/tables/{$table->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('tables', ['id' => $table->id, 'is_active' => true]);
    }

    public function test_permite_exclusao_de_mesa_sem_pedidos_em_aberto(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('store_owner', $store);
        $table = Table::factory()->create(['store_id' => $store->id, 'number' => '06']);

        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->deleteJson("/api/v1/admin/tables/{$table->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tables', ['id' => $table->id, 'is_active' => false]);
    }

    public function test_retorna_404_ao_tentar_acessar_mesa_de_outro_tenant(): void
    {
        $store1 = Store::factory()->create();
        $store2 = Store::factory()->create();
        $tableOfStore2 = Table::factory()->create(['store_id' => $store2->id]);

        $user = $this->createUser('store_owner', $store1);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store1->slug)
            ->putJson("/api/v1/admin/tables/{$tableOfStore2->id}", ['number' => '99']);

        $response->assertStatus(404);
    }

    public function test_bloqueia_acesso_sem_role_store_owner_ou_manager(): void
    {
        $store = Store::factory()->create();
        $user = $this->createUser('kitchen_staff', $store);
        Sanctum::actingAs($user);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->getJson('/api/v1/admin/tables');

        $response->assertStatus(403);
    }
}
