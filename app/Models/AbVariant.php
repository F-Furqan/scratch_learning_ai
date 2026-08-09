<?php

namespace App\Models;

use Database\Factories\AbVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ab_experiment_id', 'key', 'name', 'weight', 'views_count', 'conversions_count', 'payload'])]
class AbVariant extends Model
{
    /** @use HasFactory<AbVariantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AbExperiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(AbExperiment::class, 'ab_experiment_id');
    }

    /**
     * @return HasMany<AbAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AbAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'views_count' => 'integer',
            'conversions_count' => 'integer',
            'payload' => 'array',
        ];
    }
}
