<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckTenantRole;
use App\Http\Middleware\IdentifyTenant;
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
            IdentifyTenant::class,
        ]);
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            'role' => CheckRole::class,
            'tenant.role' => CheckTenantRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {})
    ->create();
