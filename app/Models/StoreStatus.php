<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Status operacional da loja — singleton por store (1 registro por loja).
 *
 * is_open: flag manual de abertura/fechamento
 * is_auto: quando true, is_open é calculado pelo horário em StoreSettings.opening_hours
 */
class StoreStatus extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['store_id', 'is_open', 'is_auto'];

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'is_auto' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
