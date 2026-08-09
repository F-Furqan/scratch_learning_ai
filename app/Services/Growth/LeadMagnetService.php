<?php

namespace App\Services\Growth;

use App\Enums\GrowthStatus;
use App\Enums\LeadSubmissionStatus;
use App\Models\LeadMagnet;
use App\Models\LeadSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadMagnetService
{
    /**
     * @param  array{email: string, name?: string|null, newsletter_campaign_id?: int|null}  $data
     */
    public function capture(LeadMagnet $leadMagnet, array $data, Request $request, ?User $user = null): LeadSubmission
    {
        if ($this->enumValue($leadMagnet->getAttribute('status')) !== GrowthStatus::Active->value) {
            throw ValidationException::withMessages([
                'lead_magnet' => 'This resource is not currently available.',
            ]);
        }

        return LeadSubmission::query()->updateOrCreate(
            [
                'lead_magnet_id' => $leadMagnet->id,
                'email' => strtolower($data['email']),
            ],
            [
                'newsletter_campaign_id' => $data['newsletter_campaign_id'] ?? null,
                'user_id' => $user?->id,
                'name' => $data['name'] ?? null,
                'status' => LeadSubmissionStatus::Subscribed,
                'source_url' => $request->fullUrl(),
                'metadata' => [
                    'user_agent_hash' => $request->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
                    'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
                ],
            ],
        );
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
