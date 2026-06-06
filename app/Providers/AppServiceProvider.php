<?php
namespace App\Providers;
use App\Repositories\Contracts\{CouponRepositoryInterface, OrderRepositoryInterface, ProductRepositoryInterface, StoreRepositoryInterface};
use App\Repositories\Eloquent\{CouponRepository, OrderRepository, ProductRepository, StoreRepository};
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Pré-registra o contexto de tenant com false (não null).
        // O container usa isset() internamente — isset(null) === false causaria
        // BindingResolutionException ao chamar app('current_tenant_id') sem tenant.
        $this->app->instance('current_tenant_id', false);
        $this->app->instance('current_