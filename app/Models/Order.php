<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pedido — escopo automático por tenant via BelongsToTenant.
 *
 * items (JSONB): snapshot imutável dos produtos no momento da compra.
 * Estrutura: [{ id, name, price, quantity, observations?, selections? }]
 * O snapshot preserva o preço histórico mesmo que o produto seja alterado depois.
 *
 * Fluxo de status: Realizado → Em produção → Saiu para entrega → Finalizado
 *                  Realizado → Recusado (devolve estoque via OrderService)
 */
class Order extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'store_id',
        'customer_name',
        'customer_phone',
        'address',
        'payment_method',
        'items',
        'subtotal',
        'delivery_fee',
        'coupon_code',
        'discount_amount',
        'total',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'items'           => 'array',
            'subtotal'        => 'decimal:2',
            'delivery_fee'    => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total'           => 'decimal:2',
        ];
    }

    /** Status válidos — sequência obrigatória definida pelas regras de negócio */
    const STATUSES = ['Realizado', 'Em produção', 'Saiu para entrega', 'Finalizado', 'Recusado'];

    /** Próximo status válido na sequência de progressão */
    const STATUS_FLOW = [
        'Realizado'          => 'Em produção',
        'Em produção'        => 'Saiu para entrega',
        'Saiu para entrega'  => 'Finalizado',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function couponUsage(): HasOne
    {
        return $this->hasOne(CouponUsage::class);
    }

    public function isRejected(): bool
    {
        return $this->status === 'Recusado';
    }

    public function isFinalizado(): bool
    {
        return $this->status === 'Finalizado';
    }

    public function nextStatus(): ?string
    {
        return self::STATUS_FLOW[$this->status] ?? null;
    }
}
