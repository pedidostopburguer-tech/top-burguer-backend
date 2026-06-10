<?php

namespace App\Services;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly CouponService $couponService,
    ) {}

    public function getAll(array $filters = []): Collection
    {
        return $this->orders->all($filters);
    }

    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $product = $this->products->findById($item['id']);
                if (! $product || ! $product->is_available) {
                    throw new \RuntimeException("Produto '{$item['name']}' indisponível.");
                }
                if ($product->stock_quantity !== null && $product->stock_quantity < $item['quantity']) {
                    throw new \RuntimeException("Estoque insuficiente para '{$item['name']}'.");
                }
                if ($product->stock_quantity !== null) {
                    $this->products->decrementStock($product->id, $item['quantity']);
                }
            }

            $discountAmount = 0.0;
            $deliveryFee = (float) $data['delivery_fee'];
            $couponModel = null;

            if (! empty($data['coupon_code'])) {
                $result = $this->couponService->validate($data['coupon_code'], $data['subtotal'], $data['customer_phone']);
                $couponModel = $result['coupon'];
                if ($couponModel->discount_type === 'free_delivery') {
                    $deliveryFee = 0.0;
                } else {
                    $discountAmount = $result['discount_amount'];
                }
            }

            $order = $this->orders->create([
                ...$data,
                'channel' => $data['channel'] ?? 'delivery',
                'table_number' => $data['table_number'] ?? null,
                'delivery_fee' => $deliveryFee,
                'discount_amount' => $discountAmount,
                'total' => $data['subtotal'] + $deliveryFee - $discountAmount,
                'status' => 'Realizado',
            ]);

            if ($couponModel) {
                $this->couponService->applyToOrder($couponModel->id, $data['customer_phone'], $order->id);
            }

            return $order;
        });
    }

    public function updateStatus(int $id, string $status, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($id, $status, $reason) {
            $order = $this->orders->findById($id);
            if (! $order) {
                throw new \RuntimeException('Pedido não encontrado.');
            }
            if ($status === 'Recusado' && $order->status !== 'Recusado') {
                foreach ($order->items as $item) {
                    $product = $this->products->findById($item['id']);
                    if ($product && $product->stock_quantity !== null) {
                        $this->products->restoreStock($item['id'], $item['quantity']);
                    }
                }
            }

            $updateData = [
                'status' => $status,
                'rejection_reason' => $reason,
            ];

            if ($status === 'Em produção' && ! $order->production_started_at) {
                $updateData['production_started_at'] = now();
            }

            if ($status === 'Saiu para entrega' && ! $order->dispatched_at) {
                $updateData['dispatched_at'] = now();
            }

            if ($status === 'Finalizado' && $order->channel === 'mesa' && ! $order->dispatched_at) {
                $updateData['dispatched_at'] = now();
            }

            return $this->orders->update($id, $updateData);
        });
    }

    public function submitFeedback(int $id, string $customerPhone, int $rating, ?string $feedbackText = null): Order
    {
        $order = $this->orders->findById($id);
        if (! $order) {
            throw new ModelNotFoundException('Pedido não encontrado.');
        }

        if ($order->status !== 'Finalizado') {
            throw new \InvalidArgumentException('Apenas pedidos finalizados podem ser avaliados.');
        }

        $cleanPhoneInput = preg_replace('/\D/', '', $customerPhone);
        $cleanPhoneOrder = preg_replace('/\D/', '', $order->customer_phone);

        if ($cleanPhoneInput !== $cleanPhoneOrder) {
            throw new AuthorizationException('O telefone informado não corresponde ao telefone do pedido.');
        }

        return $this->orders->update($id, [
            'rating' => $rating,
            'feedback_text' => $feedbackText,
        ]);
    }
}
