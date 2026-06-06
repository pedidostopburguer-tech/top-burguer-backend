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
        $this->app->instance('current_store', false);

        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);
    }
    public function boot(): void
    {
        // Backend é API-only — não existe a named route 'password.reset' do scaffolding web.
        // O link de reset aponta para o frontend (FRONTEND_URL), que faz POST para /api/v1/auth/reset-password.
        ResetPassword::createUrlUsing(function ($user, string $token): string {
            $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
            return $frontend . '/reset-password?token=' . $token . '&email=' . urlencode($user->getEmailForPasswordReset());
        });
    }
}
