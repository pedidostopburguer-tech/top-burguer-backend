<?php

namespace App\Models;

use App\Enums\TableStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mesa física do estabelecimento (Modo Mesa) — escopo automático por tenant via BelongsToTenant.
 *
 * `status`: controle manual do staff (livre, ocupada, limpeza).
 * `qr_token`: usado na URL do QR Code impresso (?mesa={number}&t={qr_token}), rotacionável.
 * `is_active = false`: mesa "excluída" (soft delete), preserva o histórico em
 * `orders.table_number` e permite reuso futuro do mesmo `number`.
 *
 * Unicidade: índice parcial `idx_unique_active_table_number_per_store` garante que não
 * existam duas mesas ATIVAS com o mesmo `number` na mesma loja.
 */
class Table extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'tables';

    protected $fillable = [
        'store_id',
        'number',
        'qr_token',
        'capacity',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'status' => TableStatus::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
