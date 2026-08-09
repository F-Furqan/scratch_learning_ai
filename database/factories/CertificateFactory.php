<?php

namespace Database\Factories;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'certificate_number' => 'CERT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'verification_code' => Str::lower(Str::random(32)),
            'status' => CertificateStatus::Active,
            'issued_at' => now(),
            'expires_at' => null,
            'metadata' => [],
        ];
    }
}
