<?php

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Admin\Registry\AdminResourceRegistry;

final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/admin.php', 'admin');
        $this->app->singleton(AdminResourceRegistry::class);
    }
}
