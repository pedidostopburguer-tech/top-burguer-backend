<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function all(array $filters = []): Collection;

    public function findById(int $id): ?Order;

    public function create(array $data): Order;

    public function update(int $id, array $data): Order;

    public function updateStatus(int $id, string $status, ?string $reason = null): Order;
}
