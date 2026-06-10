<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface
{
    public function allAvailable(): Collection;

    public function findById(int $id): ?Product;

    public function create(array $data): Product;

    public function update(int $id, array $data): Product;

    public function delete(int $id): bool;

    public function toggleAvailability(int $id): Product;

    public function decrementStock(int $id, int $quantity): void;

    public function restoreStock(int $id, int $quantity): void;
}
