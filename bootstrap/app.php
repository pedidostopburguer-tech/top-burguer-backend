<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \App\Http\Middleware\IdentifyTenant::class,
        ]);
        $middleware->alias([
            'tenant'      => \App\Http\Middleware\IdentifyTenant::class,
            'role'        => \App\Http\Middleware\CheckRole::class,
            'tenant.role' => \App\Http\Middleware\CheckTenantRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
