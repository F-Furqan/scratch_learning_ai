<?php

namespace App\Models;

use App\Enums\CoursePurchaseStatus;
use Carbon\CarbonInterface;
use Database\Factories\CoursePurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'course_id',
    'payment_order_id',
    'source',
    'status',
    'purchased_at',
    'expires_at',
])]
class CoursePurchase extends Model
{
    /** @use HasFactory<CoursePurchaseFactory> */
    use HasFactory;

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
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    public function isActive(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');

        return $this->getAttribute('status') === CoursePurchaseStatus::Active
            && ($expiresAt === null || ($expiresAt instanceof CarbonInterface && $expiresAt->isFuture()));
    }

    protected function casts(): array
    {
        return [
            'status' => CoursePurchaseStatus::class,
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
