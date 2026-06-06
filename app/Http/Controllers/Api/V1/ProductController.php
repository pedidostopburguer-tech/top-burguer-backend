<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\{JsonResponse, Request};

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}
    public function index(): JsonResponse { return $this->success($this->service->getAvailable()); }
    public function store(Request $request): JsonResponse {
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'required|string', 'price' => 'required|numeric|min:0', 'category_id' => 'required|integer|exists:product_categories,id', 'tag' => 'nullable|string|max:50', 'stock_quantity' => 'nullable|integer|min:0', 'stock_unit' => 'in:un,porção,g,ml', 'is_available' => 'boolean', 'image' => 'nullable|image|max:5120']);
        return $this->created($this->service->create($data, $request->file('image')));
    }
    public function update(Request $request, int $id): JsonResponse {
        $data = $request->validate(['name' => 'sometimes|string|max:255', 'description' => 'sometimes|string', 'price' => 'sometimes|numeric|min:0', 'category_id' => 'sometimes|integer|exists:product_categories,id', 'tag' => 'nullable|string|max:50', 'stock_quantity' => 'nullable|integer|min:0', 'stock_unit' => 'sometimes|in:un,porção,g,ml', 'is_available' => 'sometimes|boolean', 'image' => 'nullable|image|max:5120']);
        return $this->success($this->service->update($id, $data, $request->file('image')));
    }
    public function destroy(int $id): JsonResponse { $this->service->delete($id); return $this->success(message: 'Produto removido.'); }
    public function toggle(int $id): JsonResponse { return $this->success($this->service->toggleAvailability($id)); }
}
