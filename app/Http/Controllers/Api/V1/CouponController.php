<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\CouponRepositoryInterface;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(private readonly CouponService $service, private readonly CouponRepositoryInterface $repo) {}

    public function validate(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string', 'subtotal' => 'required|numeric|min:0', 'customer_phone' => 'required|string']);
        try {
            $r = $this->service->validate($data['code'], $data['subtotal'], $data['customer_phone']);

            return $this->success(['coupon' => $r['coupon'], 'discount_amount' => $r['discount_amount']]);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function index(): JsonResponse
    {
        return $this->success($this->repo->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:50', 'discount_type' => 'required|in:percentage,fixed,free_delivery', 'discount_value' => 'required|numeric|min:0', 'min_order_value' => 'nullable|numeric|min:0', 'max_uses' => 'nullable|integer|min:1', 'starts_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:starts_at', 'is_active' => 'boolean']);

        return $this->created($this->repo->create($data));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_active' => 'sometimes|boolean', 'expires_at' => 'nullable|date', 'max_uses' => 'nullable|integer|min:1']);

        return $this->success($this->repo->update($id, $data));
    }
}
