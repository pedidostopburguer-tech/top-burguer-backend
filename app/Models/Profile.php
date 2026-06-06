<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil de acesso (RBAC) — vincula User ao seu papel e loja.
 *
 * Roles de plataforma (store_id = null): super_admin, saas_support
 * Roles de estabelecimento (store_id preenchido): store_owner, store_manager,
 *   kitchen_staff, delivery_driver
 */
class Profile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'store_id', 'role', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Roles disponíveis no sistema */
    const ROLES = [
        // Plataforma SaaS (store_id = null)
        'super_admin',
        'saas_support',
        // Estabelecimento (store_id obrigatório)
        'store_owner',
        'store_manager',
        'kitchen_staff',
        'delivery_driver',
    ];

    /** Roles que são de plataforma (não vinculados a uma loja) */
    const PLATFORM_ROLES = ['super_admin', 'saas_support'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isPlatformRole(): bool
    {
        return in_array($this->role, self::PLATFORM_ROLES);
    }

    public function isStoreRo