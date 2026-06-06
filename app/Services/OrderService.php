<?php
namespace App\Services;
use App\Models\Order;
use App\Repositories\Contracts\{CouponRepositoryInterface, OrderRepositoryInterface, ProductRepositoryInterface};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orders,
        private readonly ProductRepositoryInterface $products,
        private readonly CouponService              $couponService,
    ) {}

    public function getAll(array $filters = []): Collection { return $this->orders->all($filters); }

    public function place(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $product = $this->products->findById($item['id']);
                if (! $product || ! $product->is_available) throw new \RuntimeException("Produto '{$item['name']}' indisponível.");
                if ($product->stock_quantity !== null && $product->stock_quantity < $item['quantity']) throw new \RuntimeException("Estoque insuficiente para '{$item['name']}'.");
                if ($product->stock_quantity !== null) $this->products->decrementStock($product->id, $item['quantity']);
            }

            $discountAmount = 0.0;
            $deliveryFee    = (float) $data['delivery_fee'];
            $couponModel    = null;

            if (! empty($data['coupon_code'])) {
                $result      = $this->couponService->validate($data['coupon_code'], $data['subtotal'], $data['customer_phone']);
                $couponModel = $result['coupon'];
                if ($couponModel->discount_type === 'free_delivery') $deliveryFee = 0.0;
                else $discountAmount = $result['discount_amount'];
            }

            $order = $this->orders->create([
                ...$data,
                'delivery_fee'    => $deliveryFee,
                'discount_amount' => $discountAmount,
                'total'           => $data['subtotal'] + $deliveryFee - $discountAmount,
                'status'          => 'Realizado',
            ]);

            if ($couponModel) $this->couponService->applyToOrder($couponModel->id, $data['customer_phone'], $order->id);
            return $order;
        });
    }

    public function updateStatus(int $id, string $status, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($id, $status, $reason) {
            $order = $this->orders->findById($id);
            if (! $order) throw new \RuntimeException('Pedido não encontrado.');
            if ($status === 'Recusado' && $order->status !== 'Recusado') {
                foreach ($order->items as $item) {
                    $product = $this->products->findById($item['id']);
                    if ($product && $product->stock_quantity !== null) $this->products->restoreStock($item['id'], $item['quantity']);
                }
            }
            return $this->orders->updateStatus($id, $status, $reason);
        });
    }
}
