<?php

namespace App\Models;

use Database\Factories\CreatorAnalyticsSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'instructor_profile_id',
    'period_date',
    'period',
    'blog_views',
    'course_views',
    'lesson_views',
    'comments_count',
    'enrollments_count',
    'course_revenue_cents',
    'ad_revenue_cents',
    'engagement_score',
    'metadata',
])]
class CreatorAnalyticsSnapshot extends Model
{
    /** @use HasFactory<CreatorAnalyticsSnapshotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<InstructorProfile, $this>
     */
    public function instructorProfile(): BelongsTo
    {
        return $this->belongsTo(InstructorProfile::class);
    }

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'blog_views' => 'integer',
            'course_views' => 'integer',
            'lesson_views' => 'integer',
            'comments_count' => 'integer',
            'enrollments_count' => 'integer',
            'course_revenue_cents' => 'integer',
            'ad_revenue_cents' => 'integer',
            'engagement_score' => 'integer',
            'metadata' => 'array',
        ];
    }
}
