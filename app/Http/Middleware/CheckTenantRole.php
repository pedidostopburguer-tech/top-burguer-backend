<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o usuário autenticado possui um role de loja E está vinculado
 * ao tenant atual (resolvido pelo IdentifyTenant middleware).
 *
 * Uso na rota:
 *   ->middleware('tenant.role:store_owner,store_manager')
 *
 * Garante que o usuário só acessa recursos da sua própria loja.
 */
class CheckTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $profile = $request->user()?->profile;
        $tenantId = app('current_tenant_id');

        $unauthorized = response()->json([
            'success' => false,
            'message' => 'Acesso não autorizado.',
            'errors' => null,
        ], 403);

        if (! $profile) {
            return $unauthorized;
        }

        // Verifica role
        if (! empty($roles) && ! in_array($profile->role, $roles)) {
            return $unauthorized;
        }

        // Roles de loja devem pertencer ao tenant atual
        if ($profile->isStoreRole()) {
            if (! $tenantId || $profile->store_id !== $tenantId) {
                return $unauthorized;
            }
        }

        return $next($request);
    }
}
