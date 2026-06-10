<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uso de cupom por pedido.
 *
 * customer_phone identifica o cliente (sem FK — clientes são anônimos no Supabase original).
 * Escopo automático por tenant via BelongsToTenant.
 */
class CouponUsage extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'store_id',
        'coupon_id',
        'customer_phone',
        'order_id',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
