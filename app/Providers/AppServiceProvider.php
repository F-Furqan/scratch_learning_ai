<?php

namespace App\Providers;

use App\Contracts\Access\AdminAccessService;
use App\Contracts\Navigation\DashboardDestinationResolver;
use App\Contracts\Payments\PaddleClient;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Access\RoleBasedAdminAccessService;
use App\Services\Navigation\RoleBasedDashboardDestinationResolver;
use App\Services\Payments\PaddleBillingClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AdminAccessService::class, RoleBasedAdminAccessService::class);
        $this->app->bind(DashboardDestinationResolver::class, RoleBasedDashboardDestinationResolver::class);
        $this->app->bind(PaddleClient::class, PaddleBillingClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureMonitoring();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure global authorization shortcuts.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });
    }

    /**
     * Configure application route rate limits.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('public-content', fn (Request $request) => Limit::perMinute(180)->by($request->ip()));
        RateLimiter::for('public-api', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('ad-tracking', fn (Request $request) => Limit::perMinute(240)->by($request->ip()));
        RateLimiter::for('payment-webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('admin-operations', function (Request $request) {
            $user = $request->user();

            return Limit::perMinute(240)->by((string) ($user instanceof User ? $user->getKey() : $request->ip()));
        });
    }

    /**
     * Configure lightweight operational monitoring hooks.
     */
    protected function configureMonitoring(): void
    {
        $slowQueryMs = (float) config('operations.monitoring.slow_query_ms', 250);

        if ($slowQueryMs <= 0) {
            return;
        }

        DB::listen(function (QueryExecuted $query) use ($slowQueryMs): void {
            if ($query->time < $slowQueryMs) {
                return;
            }

            Log::channel('monitoring')->warning('Slow database query detected.', [
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
                'sql' => $query->toRawSql(),
            ]);
        });
    }
}
