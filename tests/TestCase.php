<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private const TEST_DATABASE = 'scratch-test';

    protected function setUp(): void
    {
        $this->assertSafeProcessEnvironment();

        parent::setUp();

        $this->assertSafeApplicationEnvironment();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    private function assertSafeProcessEnvironment(): void
    {
        $environment = $this->processEnvironmentValue('APP_ENV');
        $connection = $this->processEnvironmentValue('DB_CONNECTION');
        $database = $this->processEnvironmentValue('DB_DATABASE');

        if ($environment !== 'testing' || $connection !== 'mysql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Unsafe test environment refused before application boot. Expected APP_ENV=testing, DB_CONNECTION=mysql, and DB_DATABASE=%s; received APP_ENV=%s, DB_CONNECTION=%s, DB_DATABASE=%s.',
                self::TEST_DATABASE,
                $environment ?? '<unset>',
                $connection ?? '<unset>',
                $database ?? '<unset>',
            ));
        }
    }

    private function assertSafeApplicationEnvironment(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (! app()->environment('testing') || $connection !== 'mysql' || $database !== self::TEST_DATABASE) {
            throw new RuntimeException(sprintf(
                'Unsafe Laravel test configuration refused. Expected testing/mysql/%s; received %s/%s/%s.',
                self::TEST_DATABASE,
                app()->environment(),
                (string) $connection,
                (string) $database,
            ));
        }
    }

    private function processEnvironmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) ? $value : null;
    }
}
