<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function __construct(private readonly ProductRepositoryInterface $products) {}

    public function getAvailable(): Collection
    {
        return $this->products->allAvailable();
    }

    public function create(array $data, ?UploadedFile $image = null): Product
    {
        if ($image) {
            $data['image_url'] = $this->storeImage($image);
        }

return $this->products->create($data);
    }

    public function update(int $id, array $data, ?UploadedFile $image = null): Product
    {
        if ($image) {
            $data['image_url'] = $this->storeImage($image);
        }

return $this->products->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->products->delete($id);
    }

    public function toggleAvailability(int $id): Product
    {
        return $this->products->toggleAvailability($id);
    }

    private function storeImage(UploadedFile $image): string
    {
        return Storage::url($image->store('products', 'public'));
    }
}
