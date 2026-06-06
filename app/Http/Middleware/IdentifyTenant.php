<?php
namespace App\Http\Middleware;
use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o tenant (Store) via header X-Store-Slug ou subdomínio.
 * Disponibiliza app('current_tenant_id') e app('current_store') no request.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveSlug($request);

        if ($slug) {
            $store = Store::where('slug', $slug)->where('is_active', true)->first();
            if (! $store) {
                return response()->json(['success' => false, 'message' => 'Loja não encontrada ou inativa.'], 404);
            }
            app()->instance('current_tenant_id', $store->id);
            app()->instance('current_store', $store);
            $request->merge(['_store' => $store]);
        } else {
            app()->instance('current_tenant_id', false);
            app()->instance('current_store', false);
        }

        return $next($request);
    }

    private function resolveSlug(Request $request): ?string
    {
        // 1. Header (dev local e apps mobile)
        if ($request->hasHeader('X-Store-Slug')) {
            return $request->header('X-Store-Slug');
        }
        // 2. Subdomínio (produção: minha-loja.topburguer.com.br)
        $host    = $request->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        if (str_ends_with($host, '.'.$appHost)) {
            return str($host)->before('.'.$appHost)->toString();
        }
        return null;
    }
