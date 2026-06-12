<?php

namespace App\Models;

use App\Enums\CouponDiscountType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cupom de desconto — escopo automático por tenant via BelongsToTenant.
 *
 * discount_type: enum App\Enums\CouponDiscountType (percentage | fixed | free_delivery)
 * max_uses = null → usos ilimitados
 * expires_at = null → sem expiração
 *
 * Unicidade: o índice parcial idx_unique_active_coupon_per_store garante que
 * não existam dois cupons ativos com o mesmo código na mesma loja.
 * Códigos de campanhas antigas (inativas/esgotadas) podem ser reutilizados.
 */
class Coupon extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'store_id',
        'code',
        'discount_type',
        'discount_value',
        'min_order_value',
        'max_uses',
        'current_uses',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'discount_type' => CouponDiscountType::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Verifica se o cupom é válido para uso agora.
     * Não valida pedido mínimo — isso é responsabilidade do CouponService.
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        if ($this->max_uses !== null && $this->current_uses >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calcula o valor de desconto para um subtotal e taxa de entrega dados.
     * Retorna 0.0 se o cupom não for elegível pelo pedido mínimo.
     */
    public function calculateDiscount(float $subtotal, float $deliveryFee): float
    {
        if ($subtotal < (float) $this->min_order_value) {
            return 0.0;
        }

        return match ($this->discount_type) {
            CouponDiscountType::Percentage => round($subtotal * ((float) $this->discount_value / 100), 2),
            CouponDiscountType::Fixed => min((float) $this->discount_value, $subtotal),
            CouponDiscountType::FreeDelivery => $deliveryFee,
            default => 0.0,
        };
    }
}
