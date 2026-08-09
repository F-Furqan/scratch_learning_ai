<?php

namespace App\Models;

use Database\Factories\CommunityGroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_group_id', 'user_id', 'role', 'status', 'joined_at'])]
class CommunityGroupMember extends Model
{
    /** @use HasFactory<CommunityGroupMemberFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CommunityGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CommunityGroup::class, 'community_group_id');
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
            'joined_at' => 'datetime',
        ];
    }
}
