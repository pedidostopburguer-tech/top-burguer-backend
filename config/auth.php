<?php

use App\Models\User;

return [
    // Guard padrão deve ser 'web' — usar 'api' com driver 'sanctum' causa loop infinito
    // porque o Sanctum Guard resolve internamente via $this->auth->user() que volta ao guard padrão.
    // Rotas protegidas usam auth:sanctum diretamente (o Sanctum resolve seu próprio guard).
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        // 'api' com driver:token busca coluna api_token — não usar. auth:sanctum gerencia seu próprio guard.
    ],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]],
    'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60]],
    'password_timeout' => 10800,
];
