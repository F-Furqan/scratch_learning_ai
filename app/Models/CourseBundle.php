<?php

namespace App\Models;

use App\Enums\LearningCatalogStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\CourseBundleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'price',
    'status',
    'sort_order',
    'metadata',
])]
class CourseBundle extends Model
{
    /** @use HasFactory<CourseBundleFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsToMany<Course, $this>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_bundle_courses')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_bundle_enrollments')
            ->withPivot(['status', 'started_at', 'completed_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<PaymentProduct, $this>
     */
    public function paymentProducts(): HasMany
    {
        return $this->hasMany(PaymentProduct::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => LearningCatalogStatus::class,
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
