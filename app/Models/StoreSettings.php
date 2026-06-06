<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configurações editáveis da loja — singleton por store (1 registro por loja).
 * Campos alinhados com o schema do Supabase para compatibilidade com o frontend.
 *
 * @property string $store_name
 * @property string|null $store_description
 * @property string|null $store_address
 * @property string|null $whatsapp_number
 * @property string|null $maps_url
 * @property array|null  $opening_hours    [{ day: "Segunda-feira", hours: "18:00h às 04:00h" }]
 * @property array|null  $neighborhood_fees { "centro": 5.00, "laranjeiras": 6.00 }
 * @property float       $minimum_order
 * @property float       $default_delivery_fee
 */
class StoreSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'store_name',
        'store_description',
        'store_address',
        'whatsapp_number',
        'maps_url',
        'opening_hours',
        'neighborhood_fees',
        'minimum_order',
        'default_delivery_fee',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours'        => 'array',
            'neighborhood_fees'    => 'array',
            'minimum_order'        => 'decimal:2',
            'default_delivery_fee' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
