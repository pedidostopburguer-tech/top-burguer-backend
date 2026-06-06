<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Loja (tenant). Tabela-raiz do isolamento multi-tenant.
 * Identificada no request via header X-Store-Slug → IdentifyTenant middleware.
 *
 * @property string $id   UUID
 * @property string $name
 * @property string $slug  Usado como identificador público (header X-Store-Slug)
 * @property string|null $logo_url
 * @property bool   $is_active
 */
class Store extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'logo_url', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    // ─── Relacionamentos ────────────────────────────────────────────────────

    /** Configurações editáveis (singleton) */
    public function settings(): HasOne
    {
        return $this->hasOne(StoreSettings::class);
    }

    /** Status operacional — is_open, is_auto (singleton) */
    public function status(): HasOne
    {
        return $this->hasOne(StoreStatus::class);
    }

    /** Usuários com acesso à loja (via RBAC) */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    /** Categorias do cardápio */
    public function categories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    /** Produtos do cardápio */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Pedidos recebidos */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Campanhas de cupons */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }
}
