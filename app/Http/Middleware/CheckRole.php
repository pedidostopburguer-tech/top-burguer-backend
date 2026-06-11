<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o usuário autenticado possui um dos roles permitidos.
 *
 * Uso na rota:
 *   ->middleware('role:super_admin,saas_support')
 *   ->middleware('role:store_owner,store_manager')
 */
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $profile = $request->user()?->profile;

        if (! $profile || ! in_array($profile->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Acesso não autorizado.',
                'errors' => null,
            ], 403);
        }

        return $next($request);
    }
}
