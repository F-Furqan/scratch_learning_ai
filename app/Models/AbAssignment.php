<?php

namespace App\Models;

use Database\Factories\AbAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ab_experiment_id', 'ab_variant_id', 'user_id', 'visitor_id', 'assigned_at', 'converted_at', 'metadata'])]
class AbAssignment extends Model
{
    /** @use HasFactory<AbAssignmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AbExperiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(AbExperiment::class, 'ab_experiment_id');
    }

    /**
     * @return BelongsTo<AbVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(AbVariant::class, 'ab_variant_id');
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'converted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
