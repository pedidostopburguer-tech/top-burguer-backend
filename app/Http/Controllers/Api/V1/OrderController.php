<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderFeedbackRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success(OrderResource::collection($this->service->getAll($request->only('status', 'search'))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'address' => 'required|string',
            'payment_method' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'subtotal' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string',
            'channel' => ['nullable', new Enum(OrderChannel::class)],
            'table_number' => 'nullable|string|max:50',
        ]);
        try {
            return $this->created(new OrderResource($this->service->place($data)), 'Pedido realizado com sucesso!');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:Realizado,Em produção,Saiu para entrega,Finalizado,Recusado', 'rejection_reason' => 'nullable|string']);

        return $this->success(new OrderResource($this->service->updateStatus($id, $data['status'], $data['rejection_reason'] ?? null)));
    }

    public function feedback(StoreOrderFeedbackRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $order = $this->service->submitFeedback(
                $id,
                $data['customer_phone'],
                $data['rating'],
                $data['feedback_text'] ?? null
            );

            return $this->success(new OrderResource($order), 'Avaliação registrada com sucesso.');
        } catch (ModelNotFoundException $e) {
            return $this->error('Pedido não encontrado.', 404);
        } catch (AuthorizationException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
