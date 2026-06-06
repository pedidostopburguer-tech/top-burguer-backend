<?php
namespace App\Repositories\Eloquent;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function all(array $filters = []): Collection {
        $q = Order::query()->latest();
        if (! empty($filters['status'])) $q->where('status', $filters['status']);
        if (! empty($filters['search'])) $q->where(fn($q) => $q->where('customer_name', 'ilike', '%'.$filters['search'].'%')->orWhere('customer_phone', 'like', '%'.$filters['search'].'%'));
        return $q->get();
    }
    public function findById(int $id): ?Order { return Order::find($id); }
    public function create(array $data): Order { return Order::create($data); }
    public function updateStatus(int $id, string $status, ?string $reason = null): Order { $o = Order::findOrFail($id); $o->update(['status' => $status, 'rejection_reason' => $reason]); return $o->fresh(); }
}
