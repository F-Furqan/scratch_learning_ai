<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use Database\Factories\AffiliatePartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'code', 'status', 'commission_rate_basis_points', 'cookie_days', 'payout_email', 'notes', 'metadata'])]
class AffiliatePartner extends Model
{
    /** @use HasFactory<AffiliatePartnerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AffiliateVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateVisit::class);
    }

    /**
     * @return HasMany<ReferralConversion, $this>
     */
    public function conversions(): HasMany
    {
        return $this->hasMany(ReferralConversion::class);
    }

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'commission_rate_basis_points' => 'integer',
            'cookie_days' => 'integer',
            'metadata' => 'array',
        ];
    }
}
