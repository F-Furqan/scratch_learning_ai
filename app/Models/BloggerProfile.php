<?php

namespace App\Models;

use App\Enums\BloggerStatus;
use App\Models\Concerns\HasApprovalHistories;
use Database\Factories\BloggerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $phone
 * @property string|null $profile_photo_path
 * @property string|null $bio
 * @property string|null $expertise
 * @property string|null $linkedin_url
 * @property string|null $website_url
 * @property string|null $application_reason
 * @property BloggerStatus $status
 * @property bool $is_verified_creator
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'phone',
    'profile_photo_path',
    'bio',
    'expertise',
    'linkedin_url',
    'website_url',
    'application_reason',
    'status',
    'is_verified_creator',
    'reviewed_by',
    'reviewed_at',
    'admin_notes',
])]
class BloggerProfile extends Model
{
    /** @use HasFactory<BloggerProfileFactory> */
    use HasApprovalHistories, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === BloggerStatus::Approved;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BloggerStatus::class,
            'is_verified_creator' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
