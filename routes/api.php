<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Auth (sem middleware — rate limit via throttle)
    // -------------------------------------------------------------------------
    Route::prefix('auth')->middleware('throttle:6,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // -------------------------------------------------------------------------
    // Rotas PÚBLICAS (cliente do cardápio)
    Route::get('store', [StoreController::class, 'profile']);
    Route::get('products', [ProductController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::patch('orders/{id}/feedback', [OrderController::class, 'feedback']);
    Route::post('coupons/validate', [CouponController::class, 'validate']);

    // Rotas ADMIN (autenticação via Sanctum)
    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('orders', [OrderController::class, 'index']);
        Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);

        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);
        Route::patch('products/{id}/toggle', [ProductController::class, 'toggle']);

        Route::get('coupons', [CouponController::class, 'index']);
        Route::post('coupons', [CouponController::class, 'store']);
        Route::put('coupons/{id}', [CouponController::class, 'update']);

        Route::put('store/settings', [StoreController::class, 'updateSettings']);
        Route::patch('store/status', [StoreController::class, 'updateStatus']);

        Route::middleware('tenant.role:store_owner,store_manager')->group(function () {
            Route::get('tables', [TableController::class, 'index']);
            Route::post('tables', [TableController::class, 'store']);
            Route::put('tables/{id}', [TableController::class, 'update']);
            Route::patch('tables/{id}/status', [TableController::class, 'updateStatus']);
            Route::patch('tables/{id}/rotate-qr', [TableController::class, 'rotateQr']);
            Route::delete('tables/{id}', [TableController::class, 'destroy']);
        });
    });
});
