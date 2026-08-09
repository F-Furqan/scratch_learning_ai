<?php

namespace App\Models;

use App\Enums\TeamAccountStatus;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\TeamAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'name',
    'slug',
    'status',
    'seat_limit',
    'paddle_customer_id',
    'paddle_subscription_id',
    'metadata',
])]
class TeamAccount extends Model
{
    /** @use HasFactory<TeamAccountFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<TeamSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(TeamSeat::class);
    }

    /**
     * @return HasMany<PaymentEntitlement, $this>
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(PaymentEntitlement::class);
    }

    protected function slugSourceColumn(): string
    {
        return 'name';
    }

    protected function casts(): array
    {
        return [
            'status' => TeamAccountStatus::class,
            'seat_limit' => 'integer',
            'metadata' => 'array',
        ];
    }
}
