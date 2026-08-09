<?php

namespace App\Models;

use App\Enums\CommunityReportStatus;
use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['reporter_id', 'reportable_type', 'reportable_id', 'reviewed_by', 'status', 'reason', 'details', 'reviewed_at', 'resolution_note'])]
class ContentReport extends Model
{
    /** @use HasFactory<ContentReportFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'status' => CommunityReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
