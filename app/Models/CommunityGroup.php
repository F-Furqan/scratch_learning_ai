<?php

namespace App\Models;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CommunityGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id',
    'created_by',
    'name',
    'slug',
    'description',
    'visibility',
    'status',
    'requires_paid_access',
    'members_count',
    'metadata',
])]
class CommunityGroup extends Model
{
    /** @use HasFactory<CommunityGroupFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<CommunityGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityGroupMember::class);
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'visibility' => CommunityVisibility::class,
            'status' => CommunityContentStatus::class,
            'requires_paid_access' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
