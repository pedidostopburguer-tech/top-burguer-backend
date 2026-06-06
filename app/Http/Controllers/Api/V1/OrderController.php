<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\{JsonResponse, Request};

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service) {}
    public function index(Request $request): JsonResponse { return $this->success($this->service->getAll($request->only('status', 'search'))); }
    public function store(Request $request): JsonResponse {
        $data = $request->validate(['customer_name' => 'required|string|max:255', 'customer_phone' => 'required|string|max:20', 'address' => 'required|string', 'payment_method' => 'required|string|max:100', 'items' => 'required|array|min:1', 'items.*.id' => 'required|integer', 'items.*.name' => 'required|string', 'items.*.price' => 'required|numeric|min:0', 'items.*.quantity' => 'required|integer|min:1', 'subtotal' => 'required|numeric|min:0', 'delivery_fee' => 'required|numeric|min:0', 'coupon_code' => 'nullable|string']);
        try { return $this->created($this->service->place($data), 'Pedido realizado com sucesso!'); }
        catch (\RuntimeException $e) { return $this->error($e->getMessage(), 422); }
    }
    public function updateStatus(Request $request, int $id): JsonResponse {
        $data = $request->validate(['status' => 'required|in:Realizado,Em produção,Saiu para entrega,Finalizado,Recusado', 'rejection_reason' => 'nullable|string']);
        return $this->success($this->service->updateStatus($id, $data['status'], $data['rejection_reason'] ?? null));
    }
}
