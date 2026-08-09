<?php

namespace App\Models;

use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use Database\Factories\RevenueShareRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'instructor_profile_id',
    'course_id',
    'payment_product_id',
    'type',
    'status',
    'share_percent',
    'fixed_amount_cents',
    'currency',
    'starts_at',
    'ends_at',
    'notes',
    'metadata',
])]
class RevenueShareRule extends Model
{
    /** @use HasFactory<RevenueShareRuleFactory> */
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

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<PaymentProduct, $this>
     */
    public function paymentProduct(): BelongsTo
    {
        return $this->belongsTo(PaymentProduct::class);
    }

    protected function casts(): array
    {
        return [
            'type' => RevenueShareRuleType::class,
            'status' => RevenueShareRuleStatus::class,
            'share_percent' => 'decimal:2',
            'fixed_amount_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
