<?php

namespace App\Support\Operations;

use Stringable;
use Throwable;

final class LogContextSanitizer
{
    private const SENSITIVE_KEY_PATTERN = '/(?:password|passwd|secret|token|authorization|cookie|api[_-]?key|private[_-]?key|webhook[_-]?secret)/i';

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function sanitize(array $context): array
    {
        return $this->sanitizeArray($context, 0);
    }

    public function sanitizeMessage(string $message): string
    {
        return $this->sanitizeString($message);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth >= 5) {
            return ['truncated' => true];
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = (string) $key;

            if (preg_match(self::SENSITIVE_KEY_PATTERN, $normalizedKey) === 1) {
                $sanitized[$normalizedKey] = '[REDACTED]';

                continue;
            }

            $sanitized[$normalizedKey] = $this->sanitizeValue($value, $depth + 1);
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if ($value instanceof Throwable) {
            return [
                'exception' => $value::class,
                'message' => $this->sanitizeString($value->getMessage()),
            ];
        }

        if ($value instanceof Stringable) {
            return $this->sanitizeString((string) $value);
        }

        if (is_object($value)) {
            return ['object' => $value::class];
        }

        if (is_resource($value)) {
            return '[RESOURCE]';
        }

        return is_string($value) ? $this->sanitizeString($value) : $value;
    }

    private function sanitizeString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[^\s,]+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/(pdl_(?:live|sandbox)_[A-Za-z0-9_-]+)/', '[REDACTED]', $value) ?? $value;

        return mb_substr($value, 0, 8000);
    }
}
