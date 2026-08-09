<?php

namespace App\Models;

use App\Enums\HomePageSectionType;
use Database\Factories\HomePageSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'type',
    'eyebrow',
    'title',
    'subtitle',
    'body',
    'cta_label',
    'cta_url',
    'background',
    'sort_order',
    'is_active',
    'payload',
])]
class HomePageSection extends Model
{
    /** @use HasFactory<HomePageSectionFactory> */
    use HasFactory;

    /**
     * @param  Builder<HomePageSection>  $query
     * @return Builder<HomePageSection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'type' => HomePageSectionType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'payload' => 'array',
        ];
    }
}
