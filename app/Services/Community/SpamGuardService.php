<?php

namespace App\Services\Community;

use App\Models\BlockedWord;
use Illuminate\Support\Str;

class SpamGuardService
{
    /**
     * @return array{score: int, matched_terms: list<string>, is_spam: bool}
     */
    public function inspect(string $body): array
    {
        $normalized = Str::lower(strip_tags($body));
        $matchedTerms = [];
        $score = 0;

        BlockedWord::query()
            ->where('is_active', true)
            ->get()
            ->each(function (BlockedWord $blockedWord) use ($normalized, &$matchedTerms, &$score): void {
                if (! $this->matches($normalized, $blockedWord)) {
                    return;
                }

                $matchedTerms[] = $blockedWord->word;
                $score += max(1, (int) $blockedWord->severity);
            });

        return [
            'score' => $score,
            'matched_terms' => array_values(array_unique($matchedTerms)),
            'is_spam' => $score >= 3,
        ];
    }

    private function matches(string $body, BlockedWord $blockedWord): bool
    {
        $word = Str::lower($blockedWord->word);

        return match ($blockedWord->match_type) {
            'exact' => $body === $word,
            'word' => (bool) preg_match('/\b'.preg_quote($word, '/').'\b/u', $body),
            default => str_contains($body, $word),
        };
    }
}
