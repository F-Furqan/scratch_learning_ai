<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'course_id',
    'certificate_number',
    'verification_code',
    'status',
    'issued_at',
    'expires_at',
    'metadata',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function verificationUrl(): string
    {
        return route('certificates.verify', $this->verification_code);
    }

    protected function casts(): array
    {
        return [
            'status' => CertificateStatus::class,
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
