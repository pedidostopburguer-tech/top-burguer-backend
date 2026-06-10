<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderService::class);
    }

    private function createStore(): Store
    {
        return Store::factory()->create();
    }

    public function test_define_production_started_at_ao_mudar_para_em_producao(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Realizado',
            'production_started_at' => null,
        ]);

        $updatedOrder = $this->service->updateStatus($order->id, 'Em produção');

        $this->assertNotNull($updatedOrder->production_started_at);
        $this->assertNull($updatedOrder->dispatched_at);
    }

    public function test_define_dispatched_at_ao_mudar_para_saiu_para_entrega(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Em produção',
            'dispatched_at' => null,
        ]);

        $updatedOrder = $this->service->updateStatus($order->id, 'Saiu para entrega');

        $this->assertNotNull($updatedOrder->dispatched_at);
    }

    public function test_define_dispatched_at_ao_mudar_para_finalizado_se_canal_for_mesa(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Em produção',
            'channel' => 'mesa',
            'dispatched_at' => null,
        ]);

        $updatedOrder = $this->service->updateStatus($order->id, 'Finalizado');

        $this->assertNotNull($updatedOrder->dispatched_at);
    }

    public function test_nao_altera_timestamps_se_ja_estiverem_definidos(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $pastTime = now()->utc()->subHour();

        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Em produção',
            'production_started_at' => $pastTime,
        ]);

        $updatedOrder = $this->service->updateStatus($order->id, 'Em produção');

        $this->assertEquals($pastTime->timestamp, $updatedOrder->production_started_at->timestamp);
    }

    public function test_salva_canal_e_mesa_no_checkout(): void
    {
        $store = $this->createStore();
        $this->instance('current_tenant_id', $store->id);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'price' => 30.00,
            'is_available' => true,
        ]);

        $orderData = [
            'store_id' => $store->id,
            'customer_name' => 'Alice Teste',
            'customer_phone' => '11999999999',
            'address' => 'Mesa 42',
            'payment_method' => 'Pix',
            'subtotal' => 30.00,
            'delivery_fee' => 0.00,
            'channel' => 'mesa',
            'table_number' => '42',
            'items' => [
                [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => 30.00,
                    'quantity' => 1,
                ],
            ],
        ];

        $order = $this->service->place($orderData);

        $this->assertEquals('mesa', $order->channel);
        $this->assertEquals('42', $order->table_number);
    }
}
