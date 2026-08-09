<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\AuthorBadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'icon',
    'color',
    'marks_verified_expert',
    'is_active',
    'sort_order',
])]
class AuthorBadge extends Model
{
    /** @use HasFactory<AuthorBadgeFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'author_badge_user')
            ->withPivot(['awarded_by', 'awarded_at', 'notes'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'marks_verified_expert' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
