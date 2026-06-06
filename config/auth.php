<?php
return [
    // Guard padrão deve ser 'web' — usar 'api' com driver 'sanctum' causa loop infinito
    // porque o Sanctum Guard resolve internamente via $this->auth->user() que volta ao guard padrão.
    // Rotas protegidas usam auth:sanctum diretamente (o Sanctum resolve seu próprio guard).
    'defaults'  => ['guard' => 'web', 'passwords' => 'users'],
    'guards'    => [
        'web' => ['driver' => 'session', 'provider' => 'users'],
        // 'api' com driver:token busca