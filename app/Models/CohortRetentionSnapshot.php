<?php

namespace App\Models;

use Database\Factories\CohortRetentionSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cohort_month',
    'period_number',
    'users_count',
    'retained_users_count',
    'retention_rate_basis_points',
    'metadata',
])]
class CohortRetentionSnapshot extends Model
{
    /** @use HasFactory<CohortRetentionSnapshotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cohort_month' => 'date',
            'period_number' => 'integer',
            'users_count' => 'integer',
            'retained_users_count' => 'integer',
            'retention_rate_basis_points' => 'integer',
            'metadata' => 'array',
        ];
    }
}
