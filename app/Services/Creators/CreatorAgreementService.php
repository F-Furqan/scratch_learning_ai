<?php

namespace App\Services\Creators;

use App\Models\CreatorAgreementAcceptance;
use App\Models\User;
use Illuminate\Http\Request;

class CreatorAgreementService
{
    public function currentVersion(): string
    {
        return (string) config('platform.creator_agreement.version', '2026-07-19');
    }

    public function hasAccepted(User $user, ?string $version = null): bool
    {
        $version ??= $this->currentVersion();

        return $user->creatorAgreementAcceptances()
            ->where('terms_version', $version)
            ->exists();
    }

    public function recordAcceptance(User $user, Request $request, ?string $version = null): CreatorAgreementAcceptance
    {
        $version ??= $this->currentVersion();

        return CreatorAgreementAcceptance::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'terms_version' => $version,
            ],
            [
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        );
    }
}
