<?php

namespace App\Models;

use Database\Factories\AffiliateVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['affiliate_partner_id', 'user_id', 'visitor_id', 'landing_url', 'referrer_url', 'ip_hash', 'user_agent_hash', 'clicked_at', 'expires_at'])]
class AffiliateVisit extends Model
{
    /** @use HasFactory<AffiliateVisitFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AffiliatePartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class, 'affiliate_partner_id');
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
            'clicked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
