<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Usuário autenticável via Sanctum.
 * O papel e a loja do usuário ficam no Profile vinculado.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Perfil RBAC do usuário (role + store_id) */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** Atalho: retorna a loja do usuário via profile */
    public function store(): ?Store
    {
        return $this->profile?->store;
    }
}
