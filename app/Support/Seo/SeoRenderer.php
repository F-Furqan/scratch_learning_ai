<?php

namespace App\Support\Seo;

use Illuminate\Database\Eloquent\Model;

class SeoRenderer
{
    /**
     * @return array<int, array{name?: string, property?: string, content: string}>
     */
    public function metaTags(SeoData|Model $source): array
    {
        $seo = $source instanceof Model ? SeoData::fromModel($source) : $source;
        $data = $seo->toArray();

        return array_values(array_filter([
            $this->tag('name', 'title', $data['title']),
            $this->tag('name', 'description', $data['description']),
            $this->tag('property', 'og:title', $data['open_graph']['title'] ?? null),
            $this->tag('property', 'og:description', $data['open_graph']['description'] ?? null),
            $this->tag('property', 'og:image', $data['image']),
            $this->tag('name', 'twitter:title', $data['twitter']['title'] ?? null),
            $this->tag('name', 'twitter:description', $data['twitter']['description'] ?? null),
            $this->tag('name', 'twitter:image', $data['image']),
        ]));
    }

    public function jsonLd(SeoData|Model $source): ?string
    {
        $seo = $source instanceof Model ? SeoData::fromModel($source) : $source;
        $schema = $seo->toArray()['schema'];

        if (! is_array($schema) || $schema === []) {
            return null;
        }

        return json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{name?: string, property?: string, content: string}|null
     */
    private function tag(string $attribute, string $key, ?string $content): ?array
    {
        if (blank($content)) {
            return null;
        }

        if ($attribute === 'name') {
            return [
                'name' => $key,
                'content' => $content,
            ];
        }

        return [
            'property' => $key,
            'content' => $content,
        ];
    }
}
