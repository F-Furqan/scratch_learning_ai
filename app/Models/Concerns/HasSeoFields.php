<?php

namespace App\Models\Concerns;

use App\Support\Seo\SeoData;

trait HasSeoFields
{
    public function seo(): SeoData
    {
        return SeoData::fromModel($this);
    }

    /**
     * @return array<string, string|null>
     */
    public function seoPayload(): array
    {
        return $this->seo()->toArray();
    }
}
