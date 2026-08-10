<?php

namespace App\Providers;

use App\Contracts\Access\AdminAccessService;
use App\Contracts\Navigation\DashboardDestinationResolver;
use App\Contracts\Operations\ManagedSnapshotProvider;
use App\Contracts\Payments\PaddleClient;
use App\Enums\RoleName;
use App\Models\AdCreative;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\HomeHeroSlide;
use App\Models\InstructorProfile;
use App\Models\User;
use App\Observers\MediaUsageObserver;
use App\Services\Access\RoleBasedAdminAccessService;
use App\Services\Navigation\RoleBasedDashboardDestinationResolver;
use App\Services\Operations\ApplicationHealthCheck;
use App\Services\Operations\BackupRetentionService;
use App\Services\Operations\DatabaseBackupManager;
use App\Services\Operations\DatabaseHealthCheck;
use App\Services\Operations\ManagedSnapshotDriver;
use App\Services\Operations\MySqlBackupDriver;
use App\Services\Operations\OperationalAlertNotifier;
use App\Services\Operations\PlatformHealthService;
use App\Services\Operations\QueueHealthCheck;
use App\Services\Operations\SQLiteBackupDriver;
use App\Services\Operations\StorageHealthCheck;
use App\Services\Operations\UnsupportedManagedSnapshotProvider;
use App\Services\Payments\PaddleBillingClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

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
        $this->app->bind(ManagedSnapshotProvider::class, function ($app): ManagedSnapshotProvider {
            $provider = config('operations.backups.managed.provider', UnsupportedManagedSnapshotProvider::class);

            if (! is_string($provider) || trim($provider) === '') {
                $provider = UnsupportedManagedSnapshotProvider::class;
            }

            if (! is_a($provider, ManagedSnapshotProvider::class, true)) {
                throw new RuntimeException('The configured managed snapshot provider must implement ManagedSnapshotProvider.');
            }

            return $app->make($provider);
        });

        $this->app->when([DatabaseBackupManager::class, BackupRetentionService::class])
            ->needs('$drivers')
            ->give(fn ($app): array => [
                $app->make(SQLiteBackupDriver::class),
                $app->make(MySqlBackupDriver::class),
                $app->make(ManagedSnapshotDriver::class),
            ]);

        $this->app->when(PlatformHealthService::class)
            ->needs('$checks')
            ->give(fn ($app): array => [
                $app->make(ApplicationHealthCheck::class),
                $app->make(DatabaseHealthCheck::class),
                $app->make(QueueHealthCheck::class),
                $app->make(StorageHealthCheck::class),
            ]);
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
        $this->configureQueueFailureAlerts();
        $this->configureMediaUsageTracking();
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

    /**
     * Deliver an immediate alert while scheduled monitoring remains the fallback.
     */
    protected function configureQueueFailureAlerts(): void
    {
        Event::listen(JobFailed::class, function (JobFailed $event): void {
            $uuid = $event->job->uuid() ?: hash('sha256', $event->job->getRawBody());

            app(OperationalAlertNotifier::class)->notify(
                'queue.job_failed.'.$uuid,
                'Queue job failed',
                'A queue job exhausted its configured attempts.',
                [
                    'connection' => $event->connectionName,
                    'queue' => $event->job->getQueue(),
                    'job' => $event->job->resolveName(),
                    'exception' => $event->exception::class,
                ],
            );
        });
    }

    protected function configureMediaUsageTracking(): void
    {
        foreach ([
            Course::class,
            CourseLesson::class,
            CourseResource::class,
            BlogPost::class,
            HomeHeroSlide::class,
            InstructorProfile::class,
            AdCreative::class,
        ] as $model) {
            $model::observe(MediaUsageObserver::class);
        }
    }
}
