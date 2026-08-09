<?php

namespace App\Support\Security;

use Illuminate\Support\Str;

class ContentSanitizer
{
    public function plainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = $this->removeExecutableContent($value);
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);
        $sanitized = preg_replace('/<\s*br\s*\/?>/i', "\n", $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/<\/\s*(p|div|li|h[1-6]|blockquote|section|article)\s*>/i', "\n", $sanitized) ?? $sanitized;
        $sanitized = html_entity_decode(strip_tags($sanitized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sanitized = preg_replace('/[ \t]+/', ' ', $sanitized) ?? $sanitized;
        $sanitized = preg_replace("/\n{3,}/", "\n\n", $sanitized) ?? $sanitized;

        return Str::of($sanitized)->trim()->toString();
    }

    public function preview(?string $value, int $words): ?string
    {
        $text = $this->plainText($value);

        return filled($text) ? Str::words($text, $words) : null;
    }

    private function removeExecutableContent(string $value): string
    {
        $sanitized = preg_replace(
            '#<\s*(script|style|iframe|object|embed|svg|math)[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $value,
        ) ?? $value;

        $sanitized = preg_replace(
            '#<\s*(script|style|iframe|object|embed|svg|math)[^>]*\/?\s*>#is',
            '',
            $sanitized,
        ) ?? $sanitized;

        $sanitized = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+(href|src|xlink:href)\s*=\s*("[^"]*javascript:[^"]*"|\'[^\']*javascript:[^\']*\'|[^\s>]*javascript:[^\s>]*)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+(href|src|xlink:href)\s*=\s*("[^"]*data:text\/html[^"]*"|\'[^\']*data:text\/html[^\']*\'|[^\s>]*data:text\/html[^\s>]*)/i', '', $sanitized) ?? $sanitized;

        return $sanitized;
    }
}
