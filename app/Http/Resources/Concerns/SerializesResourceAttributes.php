<?php

namespace App\Http\Resources\Concerns;

use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;

trait SerializesResourceAttributes
{
    protected function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    protected function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }

    protected function sanitizedText(?string $value): ?string
    {
        return app(ContentSanitizer::class)->plainText($value);
    }

    protected function sanitizedPreview(?string $value, int $words): ?string
    {
        return app(ContentSanitizer::class)->preview($value, $words);
    }
}
