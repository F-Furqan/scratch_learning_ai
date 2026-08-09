<?php

namespace App\Models;

use App\Enums\CopyrightTakedownStatus;
use App\Models\Concerns\HasApprovalHistories;
use Database\Factories\CopyrightTakedownRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'reportable_type',
    'reportable_id',
    'status',
    'claimant_name',
    'claimant_email',
    'claimant_company',
    'rights_owner',
    'original_work_url',
    'infringing_url',
    'content_title',
    'description',
    'good_faith_confirmed',
    'accuracy_confirmed',
    'signature',
    'ip_address',
    'user_agent',
    'reviewed_by',
    'reviewed_at',
    'resolution_note',
])]
class CopyrightTakedownRequest extends Model
{
    /** @use HasFactory<CopyrightTakedownRequestFactory> */
    use HasApprovalHistories, HasFactory;

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'status' => CopyrightTakedownStatus::class,
            'good_faith_confirmed' => 'boolean',
            'accuracy_confirmed' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
