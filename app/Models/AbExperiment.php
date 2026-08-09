<?php

namespace App\Models;

use App\Enums\GrowthStatus;
use Database\Factories\AbExperimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'surface', 'status', 'winning_variant_key', 'starts_at', 'ends_at', 'metadata'])]
class AbExperiment extends Model
{
    /** @use HasFactory<AbExperimentFactory> */
    use HasFactory;

    /**
     * @return HasMany<AbVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(AbVariant::class);
    }

    protected function casts(): array
    {
        return [
            'status' => GrowthStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
