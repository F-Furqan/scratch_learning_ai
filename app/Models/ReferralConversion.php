<?php

namespace App\Models;

use App\Enums\ReferralConversionStatus;
use Database\Factories\ReferralConversionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['affiliate_partner_id', 'affiliate_visit_id', 'user_id', 'payment_checkout_id', 'payment_order_id', 'status', 'amount', 'commission_amount', 'converted_at', 'metadata'])]
class ReferralConversion extends Model
{
    /** @use HasFactory<ReferralConversionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AffiliatePartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class, 'affiliate_partner_id');
    }

    /**
     * @return BelongsTo<AffiliateVisit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(AffiliateVisit::class, 'affiliate_visit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PaymentCheckout, $this>
     */
    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PaymentCheckout::class, 'payment_checkout_id');
    }

    /**
     * @return BelongsTo<PaymentOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ReferralConversionStatus::class,
            'amount' => 'integer',
            'commission_amount' => 'integer',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
