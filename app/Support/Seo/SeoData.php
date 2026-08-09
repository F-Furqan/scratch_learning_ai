<?php

namespace App\Support\Seo;

use Illuminate\Database\Eloquent\Model;

class SeoData
{
    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $image,
        public readonly ?string $canonicalUrl,
        public readonly ?string $ogTitle,
        public readonly ?string $ogDescription,
        public readonly ?string $twitterTitle,
        public readonly ?string $twitterDescription,
        public readonly ?array $schema = null,
    ) {}

    public static function fromModel(Model $model): self
    {
        return new self(
            title: self::value($model, 'seo_title') ?: self::value($model, 'title') ?: self::value($model, 'name'),
            description: self::value($model, 'seo_description') ?: self::value($model, 'excerpt') ?: self::value($model, 'short_description'),
            image: self::value($model, 'seo_image'),
            canonicalUrl: self::value($model, 'canonical_url'),
            ogTitle: self::value($model, 'og_title'),
            ogDescription: self::value($model, 'og_description'),
            twitterTitle: self::value($model, 'twitter_title'),
            twitterDescription: self::value($model, 'twitter_description'),
            schema: self::arrayValue($model, 'schema'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $openGraphTitle = $this->ogTitle ?: $this->title;
        $openGraphDescription = $this->ogDescription ?: $this->description;
        $twitterTitle = $this->twitterTitle ?: $this->title;
        $twitterDescription = $this->twitterDescription ?: $this->description;

        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'canonical_url' => $this->canonicalUrl,
            'og_title' => $openGraphTitle,
            'og_description' => $openGraphDescription,
            'twitter_title' => $twitterTitle,
            'twitter_description' => $twitterDescription,
            'open_graph' => [
                'title' => $openGraphTitle,
                'description' => $openGraphDescription,
                'image' => $this->image,
            ],
            'twitter' => [
                'title' => $twitterTitle,
                'description' => $twitterDescription,
                'image' => $this->image,
            ],
            'schema' => $this->schema,
        ];
    }

    private static function value(Model $model, string $key): ?string
    {
        $value = $model->getAttribute($key);

        return is_string($value) && filled($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function arrayValue(Model $model, string $key): ?array
    {
        $value = $model->getAttribute($key);

        return is_array($value) && $value !== [] ? $value : null;
    }
}
