<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function createStore(): Store
    {
        return Store::factory()->create();
    }

    public function test_registra_avaliacao_do_pedido_com_sucesso(): void
    {
        $store = $this->createStore();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Finalizado',
            'customer_phone' => '11999999999',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/orders/{$order->id}/feedback", [
                'customer_phone' => '11999999999',
                'rating' => 5,
                'feedback_text' => 'Hambúrguer delicioso e entrega super rápida!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback_text', 'Hambúrguer delicioso e entrega super rápida!');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'rating' => 5,
            'feedback_text' => 'Hambúrguer delicioso e entrega super rápida!',
        ]);
    }

    public function test_normaliza_telefone_ao_avaliar(): void
    {
        $store = $this->createStore();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Finalizado',
            'customer_phone' => '11999999999',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/orders/{$order->id}/feedback", [
                'customer_phone' => '(11) 99999-9999',
                'rating' => 4,
                'feedback_text' => 'Muito bom!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 4);
    }

    public function test_rejeita_avaliacao_se_o_telefone_nao_bater(): void
    {
        $store = $this->createStore();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Finalizado',
            'customer_phone' => '11999999999',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/orders/{$order->id}/feedback", [
                'customer_phone' => '11988888888',
                'rating' => 5,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'O telefone informado não corresponde ao telefone do pedido.');
    }

    public function test_rejeita_avaliacao_se_o_pedido_nao_estiver_finalizado(): void
    {
        $store = $this->createStore();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Em produção',
            'customer_phone' => '11999999999',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/orders/{$order->id}/feedback", [
                'customer_phone' => '11999999999',
                'rating' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Apenas pedidos finalizados podem ser avaliados.');
    }

    public function test_permite_sobrescrever_avaliacao_anterior(): void
    {
        $store = $this->createStore();
        $order = Order::factory()->create([
            'store_id' => $store->id,
            'status' => 'Finalizado',
            'customer_phone' => '11999999999',
            'rating' => 3,
            'feedback_text' => 'Regular',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store->slug)
            ->patchJson("/api/v1/orders/{$order->id}/feedback", [
                'customer_phone' => '11999999999',
                'rating' => 5,
                'feedback_text' => 'Melhorou muito!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.feedback_text', 'Melhorou muito!');
    }

    public function test_nao_vaza_dados_de_outro_tenant(): void
    {
        $store1 = $this->createStore();
        $store2 = $this->createStore();

        $orderOfStore2 = Order::factory()->create([
            'store_id' => $store2->id,
            'status' => 'Finalizado',
            'customer_phone' => '11999999999',
        ]);

        $response = $this->withHeader('X-Store-Slug', $store1->slug)
            ->patchJson("/api/v1/orders/{$orderOfStore2->id}/feedback", [
                'customer_phone' => '11999999999',
                'rating' => 5,
            ]);

        $response->assertStatus(404);
    }
}
