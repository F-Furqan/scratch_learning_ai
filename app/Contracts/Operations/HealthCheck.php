<?php

namespace App\Contracts\Operations;

use App\Support\Operations\HealthCheckResult;

interface HealthCheck
{
    public function name(): string;

    public function run(): HealthCheckResult;
}
