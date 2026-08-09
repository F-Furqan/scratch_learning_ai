<?php

namespace App\Support\PublicSite;

use App\Enums\HomeHeroMode;
use App\Models\HomeHeroSlide;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class HomeHeroPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $config = $this->config();
        $mode = HomeHeroMode::tryFrom((string) ($config['mode'] ?? '')) ?? HomeHeroMode::Design;

        return [
            'mode' => $mode->value,
            'design' => [
                'eyebrow' => (string) ($config['eyebrow'] ?? 'The leader in online learning'),
                'heading' => (string) ($config['heading'] ?? 'Find the best courses from expert mentors.'),
                'highlight_terms' => $this->highlightTerms($config['highlight_terms'] ?? ['courses', 'mentors']),
                'description' => $config['description'] ?? null,
                'search_enabled' => $this->booleanValue($config['search_enabled'] ?? true),
                'stats_enabled' => $this->booleanValue($config['stats_enabled'] ?? true),
                'featured_course_enabled' => $this->booleanValue($config['featured_course_enabled'] ?? true),
            ],
            'slides' => $this->slides(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $setting = SiteSetting::query()
            ->where('key', 'home.hero')
            ->first();

        if (! $setting) {
            return [];
        }

        $value = $setting->getAttribute('value');

        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slides(): array
    {
        return HomeHeroSlide::query()
            ->active()
            ->with('mediaAsset')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (HomeHeroSlide $slide): array => [
                'id' => $slide->id,
                'eyebrow' => $slide->eyebrow,
                'title' => $slide->title,
                'subtitle' => $slide->subtitle,
                'button_label' => $slide->button_label ?: 'Explore now',
                'target_url' => $slide->target_url,
                'image_url' => $slide->mediaAsset?->url ?: ($slide->image_url ?: '/brand/home-hero-slide-learning.png'),
                'image_alt' => $slide->image_alt ?: $slide->title,
                'text_position' => $slide->text_position,
                'opens_in_new_tab' => $slide->opens_in_new_tab,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function highlightTerms(mixed $terms): array
    {
        if ($terms instanceof Collection) {
            $terms = $terms->all();
        }

        if (is_string($terms)) {
            $terms = explode(',', $terms);
        }

        if (! is_array($terms)) {
            return ['courses', 'mentors'];
        }

        return collect($terms)
            ->map(fn (mixed $term): string => trim((string) $term))
            ->filter()
            ->values()
            ->all();
    }

    private function booleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
