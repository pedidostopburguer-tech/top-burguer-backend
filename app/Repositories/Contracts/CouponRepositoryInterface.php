<?php

namespace App\Repositories\Contracts;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Collection;

interface CouponRepositoryInterface
{
    public function findByCode(string $code): ?Coupon;

    public function all(): Collection;

    public function create(array $data): Coupon;

    public function update(int $id, array $data): Coupon;

    public function logUsage(int $couponId, string $phone, int $orderId): void;

    public function hasUsedCoupon(int $couponId, string $phone): bool;
}
