<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Creators\CreatorAgreementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatorAgreementController extends Controller
{
    public function __construct(
        private readonly CreatorAgreementService $agreements,
    ) {}

    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('creator/Agreement', [
            'agreement' => [
                'version' => $this->agreements->currentVersion(),
                'accepted' => $this->agreements->hasAccepted($user),
                'terms_url' => route('public.pages.terms', absolute: false),
                'privacy_url' => route('public.pages.privacy', absolute: false),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'creator_agreement_accepted' => ['accepted'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($validated['creator_agreement_accepted']) {
            $this->agreements->recordAcceptance($user, $request);
        }

        return redirect()->intended(route('creator.dashboard', absolute: false));
    }
}
