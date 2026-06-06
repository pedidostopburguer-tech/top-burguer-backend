<?php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categorias do cardápio — escopo automático por tenant via BelongsToTenant.
 * Slug único por loja (índice composto store_id + slug na migration).
 *
 * Categorias padrão do Supabase: burgers, combos, drinks, extras, sauces, portions
 */
class ProductCategory extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['store_id', 'name', 'slug'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
