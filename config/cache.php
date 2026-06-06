<?php
use Illuminate\Support\Str;
return [
    'default' => env('CACHE_STORE', 'redis'),
    'stores'  => [
        'file'  => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],
        'redis' => ['driver' => 'redis', 'connection' => 'cache', 'lock_connection' => 'default'],
    ],
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'top_burguer'), '_').'_cache_'),
];
