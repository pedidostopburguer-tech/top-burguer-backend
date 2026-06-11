<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(private readonly CouponRepositoryInterface $coupons) {}

    public function validate(string $code, float $subtotal, string $customerPhone): array
    {
        $coupon = $this->coupons->findByCode($code);
        if (! $coupon) {
            throw new \InvalidArgumentException('Cupom não encontrado ou inativo.');
        }
        if (! $coupon->isValid()) {
            throw new \InvalidArgumentException('Este cupom está expirado ou esgotado.');
        }
        if ($subtotal < $coupon->min_order_value) {
            throw new \InvalidArgumentException(sprintf('Subtotal mínimo para este cupom: R$ %.2f.', $coupon->min_order_value));
        }
        if ($this->coupons->hasUsedCoupon($coupon->id, $customerPhone)) {
            throw new \InvalidArgumentException('Você já utilizou este cupom.');
        }

        return ['coupon' => $coupon, 'discount_amount' => $this->calculateDiscount($coupon, $subtotal)];
    }

    public function applyToOrder(int $couponId, string $phone, int $orderId): void
    {
        DB::transaction(fn () => $this->coupons->logUsage($couponId, $phone, $orderId));
    }

    private function calculateDiscount(Coupon $coupon, float $subtotal): float
    {
        return match ($coupon->discount_type) {
            'percentage' => round($subtotal * ($coupon->discount_value / 100), 2),
            'fixed' => min((float) $coupon->discount_value, $subtotal),
            'free_delivery' => 0.0,
            default => 0.0,
        };
    }
}
