<?php

namespace App\Models;

use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\PaymentProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id',
    'course_bundle_id',
    'name',
    'slug',
    'description',
    'type',
    'status',
    'paddle_product_id',
    'tax_category',
    'metadata',
])]
class PaymentProduct extends Model
{
    /** @use HasFactory<PaymentProductFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<CourseBundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(CourseBundle::class, 'course_bundle_id');
    }

    /**
     * @return HasMany<PaymentPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PaymentPrice::class);
    }

    public function isActive(): bool
    {
        return $this->getAttribute('status') === PaymentProductStatus::Active;
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'type' => PaymentProductType::class,
            'status' => PaymentProductStatus::class,
            'metadata' => 'array',
        ];
    }
}
