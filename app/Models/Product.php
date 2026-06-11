<?php

namespace App\Models;

use App\Enums\ProductStockUnit;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produto do cardápio — escopo automático por tenant via BelongsToTenant.
 *
 * stock_quantity = null → estoque ilimitado (nunca esgota)
 * stock_quantity = 0    → esgotado (is_available vira false no OrderService)
 *
 * stock_unit: enum App\Enums\ProductStockUnit (un | porção | g | ml)
 */
class Product extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'price',
        'tag',
        'stock_quantity',
        'stock_unit',
        'is_available',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_available' => 'boolean',
            'stock_unit' => ProductStockUnit::class,
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** Verifica se o produto tem estoque suficiente para a quantidade solicitada */
    public function hasStock(int $quantity): bool
    {
        if ($this->stock_quantity === null) {
            return true; // ilimitado
        }

        return $this->stock_quantity >= $quantity;
    }
}
