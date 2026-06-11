<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckTenantRole;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            IdentifyTenant::class,
        ]);
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'role' => CheckRole::class,
            'tenant.role' => CheckTenantRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Padroniza erros de validação de FormRequests no formato { success, message, errors }
        // usado em toda a API (ver CLAUDE.md "Padrão de resposta da API"). Sem isso, o Laravel
        // retorna { message, errors } puro para requests JSON, quebrando o contrato.
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });
    })
    ->create();
