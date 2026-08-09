<?php

namespace App\Models;

use App\Enums\AnalyticsEventType;
use Database\Factories\AnalyticsEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'session_id',
    'visitor_id',
    'event_type',
    'event_name',
    'eventable_type',
    'eventable_id',
    'course_id',
    'course_lesson_id',
    'blog_post_id',
    'payment_checkout_id',
    'payment_order_id',
    'url',
    'referrer_url',
    'source',
    'occurred_at',
    'metadata',
])]
class AnalyticsEvent extends Model
{
    /** @use HasFactory<AnalyticsEventFactory> */
    use HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseLesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    /**
     * @return BelongsTo<BlogPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    protected function casts(): array
    {
        return [
            'event_type' => AnalyticsEventType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
