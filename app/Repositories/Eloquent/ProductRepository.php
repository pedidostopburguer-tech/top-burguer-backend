<?php
namespace App\Repositories\Eloquent;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function allAvailable(): Collection { return Product::where('is_available', true)->with('category')->orderBy('name')->get(); }
    public function findById(int $id): ?Product { return Product::find($id); }
    public function create(array $data): Product { return Product::create($data); }
    public function update(int $id, array $data): Product { $p = Product::findOrFail($id); $p->update($data); return $p->fresh(); }
    public function delete(int $id): bool { return Product::findOrFail($id)->delete(); }
    public function toggleAvailability(int $id): Product { $p = Product::findOrFail($id); $p->update(['is_available' => ! $p->is_available]); return $p->fresh(); }
    public function decrementStock(int $id, int $quantity): void { Product::where('id', $id)->decrement('stock_quantity', $quantity); }
    public function restoreStock(int $id, int $quantity): void {
        Product::where('id', $id)->increment('stock_quantity', $quantity);
        Product::where('id', $id)->where('stock_quantity', '>', 0)->update(['is_available' => true]);
    }
}
