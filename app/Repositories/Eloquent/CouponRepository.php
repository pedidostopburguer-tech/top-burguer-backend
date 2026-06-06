<?php
namespace App\Repositories\Eloquent;
use App\Models\{Coupon, CouponUsage};
use App\Repositories\Contracts\CouponRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CouponRepository implements CouponRepositoryInterface
{
    public function findByCode(string $code): ?Coupon { return Coupon::where('code', strtoupper($code))->where('is_active', true)->first(); }
    public function all(): Collection { return Coupon::orderByDesc('created_at')->get(); }
    public function create(array $data): Coupon { return Coupon::create($data); }
    public function update(int $id, array $data): Coupon { $c = Coupon::findOrFail($id); $c->update($data); return $c->fresh(); }
    public function logUsage(int $couponId, string $phone, int $orderId): void {
        CouponUsage::create(['coupon_id' => $couponId, 'customer_phone' => $phone, 'order_id' => $orderId, 'used_at' => now()]);
        Coupon::where('id', $couponId)->increment('current_uses');
    }
    public function hasUsedCoupon(int $couponId, string $phone): bool { return CouponUsage::where('coupon_id', $couponId)->where('customer_phone', $phone)->exists(); }
}
