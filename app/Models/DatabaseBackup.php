<?php

namespace App\Models;

use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $driver
 * @property string $connection_name
 * @property string $database_name
 * @property DatabaseBackupStatus $status
 * @property BackupVerificationStatus $verification_status
 * @property string|null $disk
 * @property string|null $path
 * @property string|null $provider_reference
 * @property int|null $bytes
 * @property string|null $checksum
 * @property string|null $failure_reason
 * @property string|null $verification_failure_reason
 * @property array<string, mixed>|null $metadata
 */
#[Fillable([
    'driver',
    'connection_name',
    'database_name',
    'status',
    'verification_status',
    'disk',
    'path',
    'provider_reference',
    'bytes',
    'checksum',
    'failure_reason',
    'verification_failure_reason',
    'started_at',
    'completed_at',
    'verified_at',
    'storage_deleted_at',
    'metadata',
])]
class DatabaseBackup extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DatabaseBackupStatus::class,
            'verification_status' => BackupVerificationStatus::class,
            'bytes' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'verified_at' => 'datetime',
            'storage_deleted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
