<?php

namespace App\Models;

use Database\Factories\AnalyticsFunnelSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'funnel_key',
    'period_date',
    'period',
    'stage',
    'stage_order',
    'visitors_count',
    'users_count',
    'conversions_count',
    'conversion_rate_basis_points',
    'metadata',
])]
class AnalyticsFunnelSnapshot extends Model
{
    /** @use HasFactory<AnalyticsFunnelSnapshotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'stage_order' => 'integer',
            'visitors_count' => 'integer',
            'users_count' => 'integer',
            'conversions_count' => 'integer',
            'conversion_rate_basis_points' => 'integer',
            'metadata' => 'array',
        ];
    }
}
