<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'location', 'is_active'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
