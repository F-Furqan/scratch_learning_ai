<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function (self $model): void {
            $slugColumn = $model->slugColumn();
            $sourceColumn = $model->slugSourceColumn();
            $slug = $model->getAttribute($slugColumn);

            if (filled($slug) && ! $model->isDirty($slugColumn)) {
                return;
            }

            $base = Str::slug((string) ($slug ?: $model->getAttribute($sourceColumn)));

            if (blank($base)) {
                $base = Str::lower(Str::random(8));
            }

            $model->setAttribute($slugColumn, $model->makeUniqueSlug($base));
        });
    }

    protected function slugColumn(): string
    {
        return 'slug';
    }

    protected function slugSourceColumn(): string
    {
        return 'title';
    }

    /**
     * @return list<string>
     */
    protected function uniqueSlugScopeColumns(): array
    {
        return [];
    }

    protected function makeUniqueSlug(string $base): string
    {
        $slugColumn = $this->slugColumn();
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $slugColumn)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, string $slugColumn): bool
    {
        $query = static::query()->where($slugColumn, $slug);

        foreach ($this->uniqueSlugScopeColumns() as $column) {
            $query->where($column, $this->getAttribute($column));
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }
}
