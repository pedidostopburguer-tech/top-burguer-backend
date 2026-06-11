<?php

namespace App\Repositories\Eloquent;

use App\Enums\TableStatus;
use App\Models\Order;
use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TableRepository implements TableRepositoryInterface
{
    public function all(?string $status = null, bool $onlyActive = true): Collection
    {
        return Table::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('number')
            ->get();
    }

    public function findById(int $id): ?Table
    {
        return Table::find($id);
    }

    public function create(array $data): Table
    {
        $data['qr_token'] = $this->generateUniqueQrToken();
        $data['status'] = TableStatus::Livre;
        $data['is_active'] = true;

        return Table::create($data);
    }

    public function update(int $id, array $data): Table
    {
        $table = Table::findOrFail($id);
        $table->update($data);

        return $table->fresh();
    }

    public function updateStatus(int $id, string $status): Table
    {
        $table = Table::findOrFail($id);
        $table->update(['status' => $status]);

        return $table->fresh();
    }

    public function rotateQrToken(int $id): Table
    {
        $table = Table::findOrFail($id);
        $table->update(['qr_token' => $this->generateUniqueQrToken()]);

        return $table->fresh();
    }

    public function deactivate(int $id): Table
    {
        $table = Table::findOrFail($id);
        $table->update(['is_active' => false]);

        return $table->fresh();
    }

    public function existsByNumber(string $number, ?int $excludeId = null): bool
    {
        return Table::where('number', $number)
            ->where('is_active', true)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public function hasOpenOrder(string $number): bool
    {
        return Order::where('channel', 'mesa')
            ->where('table_number', $number)
            ->whereNotIn('status', ['Finalizado', 'Recusado'])
            ->exists();
    }

    /**
     * `qr_token` é único globalmente (índice de banco não é escopado por tenant), então a
     * verificação de unicidade aqui precisa ignorar o global scope do BelongsToTenant.
     */
    private function generateUniqueQrToken(): string
    {
        do {
            $token = Str::random(12);
        } while (Table::withoutGlobalScopes()->where('qr_token', $token)->exists());

        return $token;
    }
}
