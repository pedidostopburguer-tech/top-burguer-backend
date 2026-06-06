<?php
use App\Http\Controllers\Api\V1\{AuthController, CouponController, OrderController, ProductController, StoreController};
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Auth (sem middleware — rate limit via throttle)
    // -------------------------------------------------------------------------
    Route::prefix('auth')->middleware('throttle:6,1')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    // -------------------------------------------------------------------------
    // Rotas PÚBLICAS (cliente do cardápio)
    Route::get('store', [StoreController::class, 'profile']);
    Route::get('products', [ProductController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('coupons/validate', [CouponController::class, 'validate']);

    // Rotas ADMIN (autenticação via S