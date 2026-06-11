<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(private readonly StoreService $service) {}

    public function profile(): JsonResponse
    {
        return $this->success($this->service->getPublicProfile());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['store_name' => 'sometimes|string|max:255', 'slogan' => 'sometimes|string|max:255', 'address' => 'sometimes|string', 'phone' => 'sometimes|string|max:20', 'maps_url' => 'sometimes|url|nullable', 'opening_hours' => 'sometimes|array', 'delivery_fees' => 'sometimes|array', 'min_order_value' => 'sometimes|numeric|min:0']);

        return $this->success($this->service->updateSettings($data));
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['is_open' => 'required|boolean', 'is_auto' => 'required|boolean']);

        return $this->success($this->service->updateStatus($data['is_open'], $data['is_auto']));
    }
}
