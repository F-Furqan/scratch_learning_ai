<?php

namespace App\Logging;

use App\Models\ApplicationLogEntry;
use App\Support\Operations\LogContextSanitizer;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

final class DatabaseLogHandler extends AbstractProcessingHandler
{
    private static bool $writing = false;

    public function __construct(
        private readonly LogContextSanitizer $sanitizer,
        private readonly string $environment,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (self::$writing) {
            return;
        }

        self::$writing = true;

        try {
            ApplicationLogEntry::query()->create([
                'level' => strtolower($record->level->getName()),
                'channel' => mb_substr($record->channel, 0, 80),
                'environment' => mb_substr($this->environment, 0, 40),
                'message' => $this->sanitizer->sanitizeMessage($record->message),
                'context' => $this->sanitizer->sanitize($record->context),
                'occurred_at' => $record->datetime,
            ]);
        } catch (\Throwable) {
            // Logging must never interrupt the application or recurse into itself.
        } finally {
            self::$writing = false;
        }
    }
}
