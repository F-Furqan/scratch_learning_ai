<?php

namespace App\Models;

use App\Enums\InstructorProfileStatus;
use App\Models\Concerns\HasApprovalHistories;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\InstructorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'avatar_media_id',
    'reviewed_by',
    'display_name',
    'slug',
    'headline',
    'bio',
    'credentials',
    'expertise',
    'website_url',
    'linkedin_url',
    'status',
    'is_verified_expert',
    'accepts_revenue_share',
    'payout_currency',
    'payout_account_reference',
    'reviewed_at',
    'admin_notes',
    'metadata',
])]
class InstructorProfile extends Model
{
    /** @use HasFactory<InstructorProfileFactory> */
    use HasApprovalHistories, HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<CreatorAnalyticsSnapshot, $this>
     */
    public function analyticsSnapshots(): HasMany
    {
        return $this->hasMany(CreatorAnalyticsSnapshot::class);
    }

    /**
     * @return HasMany<RevenueShareRule, $this>
     */
    public function revenueShareRules(): HasMany
    {
        return $this->hasMany(RevenueShareRule::class);
    }

    public function isApproved(): bool
    {
        $status = $this->getAttribute('status');

        return $status instanceof InstructorProfileStatus
            ? $status === InstructorProfileStatus::Approved
            : $status === InstructorProfileStatus::Approved->value;
    }

    protected function slugSourceColumn(): string
    {
        return 'display_name';
    }

    protected function casts(): array
    {
        return [
            'status' => InstructorProfileStatus::class,
            'is_verified_expert' => 'boolean',
            'accepts_revenue_share' => 'boolean',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
