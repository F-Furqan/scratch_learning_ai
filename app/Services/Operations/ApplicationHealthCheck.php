<?php

namespace App\Services\Operations;

use App\Contracts\Operations\HealthCheck;
use App\Support\Operations\HealthCheckResult;
use Illuminate\Foundation\Application;

class ApplicationHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'application';
    }

    public function run(): HealthCheckResult
    {
        return new HealthCheckResult($this->name(), true, [
            'framework' => 'Laravel',
            'version' => Application::VERSION,
        ]);
    }
}
