<?php

namespace App\Logging;

use App\Support\Operations\LogContextSanitizer;
use Monolog\Level;
use Monolog\Logger;

final readonly class DatabaseLoggerFactory
{
    public function __construct(private LogContextSanitizer $sanitizer) {}

    /** @param array<string, mixed> $config */
    public function __invoke(array $config): Logger
    {
        $level = match (strtolower((string) ($config['level'] ?? 'warning'))) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Warning,
        };

        return new Logger('application', [
            new DatabaseLogHandler(
                $this->sanitizer,
                (string) app()->environment(),
                $level,
            ),
        ]);
    }
}
