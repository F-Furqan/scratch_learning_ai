<?php

namespace App\Models;

use App\Enums\TeamSeatStatus;
use Database\Factories\TeamSeatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_account_id',
    'user_id',
    'email',
    'role',
    'status',
    'invited_at',
    'accepted_at',
])]
class TeamSeat extends Model
{
    /** @use HasFactory<TeamSeatFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TeamAccount, $this>
     */
    public function teamAccount(): BelongsTo
    {
        return $this->belongsTo(TeamAccount::class);
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
            'status' => TeamSeatStatus::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
