<?php

namespace App\Models;

use App\Enums\AdvertiserRequestStatus;
use Database\Factories\AdvertiserRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'requested_ad_zone_id',
    'company_name',
    'contact_name',
    'email',
    'phone',
    'website_url',
    'budget_min',
    'budget_max',
    'message',
    'status',
    'reviewed_by',
    'reviewed_at',
    'admin_notes',
    'metadata',
])]
class AdvertiserRequest extends Model
{
    /** @use HasFactory<AdvertiserRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AdZone, $this>
     */
    public function requestedZone(): BelongsTo
    {
        return $this->belongsTo(AdZone::class, 'requested_ad_zone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdvertiserRequestStatus::class,
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
