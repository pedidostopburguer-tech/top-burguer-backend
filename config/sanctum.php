<?php
use Laravel\Sanctum\Sanctum;
return [
    'stateful'   => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:5173,127.0.0.1,::1')),
    // 'web' = guard para SPA (cookie/sessão). Nunca usar 'api' com driver:token aqui
    // pois o Sanctum Guard chamaria $auth->guard('api')->user() que busca coluna api_token.
    'guard'      => ['web'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encry