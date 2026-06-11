<?php

namespace Tests\Unit\Services;

use App\Exceptions\TableHasOpenOrderException;
use App\Models\Order;
use App\Models\Store;
use App\Models\Table;
use App\Services\TableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableServiceTest extends TestCase
{
    use RefreshDatabase;

    private TableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TableService::class);
    }

    private function createStore(): Store
    {
        return Store::factory()->create();
    }

    public function test_gera_qr_token_unico_ao_criar_mesa(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $table1 = $this->service->create(['number' => '01']);
        $table2 = $this->service->create(['number' => '02']);

        $this->assertNotEmpty($table1->qr_token);
        $this->assertNotEmpty($table2->qr_token);
        $this->assertNotEquals($table1->qr_token, $table2->qr_token);
    }

    public function test_detecta_pedido_em_aberto_corretamente_para_bloquear_exclusao(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $table = Table::factory()->create(['store_id' => $store->id, 'number' => '07']);

        Order::factory()->create([
            'store_id' => $store->id,
            'channel' => 'mesa',
            'table_number' => '07',
            'status' => 'Em produção',
        ]);

        $this->expectException(TableHasOpenOrderException::class);

        $this->service->deactivate($table->id);
    }

    public function test_considera_finalizado_e_recusado_como_sem_pedido_em_aberto(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $table = Table::factory()->create(['store_id' => $store->id, 'number' => '08']);

        Order::factory()->create([
            'store_id' => $store->id,
            'channel' => 'mesa',
            'table_number' => '08',
            'status' => 'Finalizado',
        ]);

        Order::factory()->create([
            'store_id' => $store->id,
            'channel' => 'mesa',
            'table_number' => '08',
            'status' => 'Recusado',
        ]);

        $deactivated = $this->service->deactivate($table->id);

        $this->assertFalse($deactivated->is_active);
    }
}
