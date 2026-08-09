<?php

namespace App\Http\Controllers\Public;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use BackedEnum;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

class CertificateVerificationController extends Controller
{
    public function __invoke(Certificate $certificate): Response
    {
        $certificate->loadMissing(['user', 'course']);
        $isValid = $this->enumValue($certificate->getAttribute('status')) === CertificateStatus::Active->value
            && $this->isFutureOrEmpty($certificate->getAttribute('expires_at'));

        return Inertia::render('public/certificates/Verify', [
            'certificate' => [
                'certificate_number' => $certificate->certificate_number,
                'student_name' => $certificate->user?->name,
                'course_title' => $certificate->course?->title,
                'course_url' => $certificate->course ? route('public.courses.show', $certificate->course->slug) : null,
                'status' => $this->enumValue($certificate->getAttribute('status')),
                'is_valid' => $isValid,
                'issued_at' => $this->isoDate($certificate->getAttribute('issued_at')),
                'expires_at' => $this->isoDate($certificate->getAttribute('expires_at')),
            ],
            'seo' => [
                'title' => 'Certificate Verification',
                'description' => 'Verify a course completion certificate.',
                'canonical_url' => $certificate->verificationUrl(),
                'image' => null,
                'open_graph' => [
                    'title' => 'Certificate Verification',
                    'description' => 'Verify a course completion certificate.',
                    'image' => null,
                ],
                'twitter' => [
                    'title' => 'Certificate Verification',
                    'description' => 'Verify a course completion certificate.',
                    'image' => null,
                ],
                'structured_data' => [],
            ],
        ]);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }

    private function isFutureOrEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if ($value instanceof CarbonInterface) {
            return $value->isFuture();
        }

        return false;
    }
}
