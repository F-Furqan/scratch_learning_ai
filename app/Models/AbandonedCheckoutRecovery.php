<?php

namespace App\Models;

use App\Enums\CheckoutRecoveryStatus;
use Database\Factories\AbandonedCheckoutRecoveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_checkout_id', 'user_id', 'email', 'status', 'recovery_token', 'recovery_url', 'reminder_count', 'last_reminded_at', 'recovered_at', 'expires_at', 'metadata'])]
class AbandonedCheckoutRecovery extends Model
{
    /** @use HasFactory<AbandonedCheckoutRecoveryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PaymentCheckout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CheckoutRecoveryStatus::class,
            'reminder_count' => 'integer',
            'last_reminded_at' => 'datetime',
            'recovered_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
