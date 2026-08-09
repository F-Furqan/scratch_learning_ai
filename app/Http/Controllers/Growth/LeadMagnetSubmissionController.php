<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\LeadMagnet;
use App\Services\Growth\LeadMagnetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadMagnetSubmissionController extends Controller
{
    public function __construct(
        private readonly LeadMagnetService $leads,
    ) {}

    public function store(Request $request, LeadMagnet $leadMagnet): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'newsletter_campaign_id' => ['nullable', 'integer', Rule::exists('newsletter_campaigns', 'id')],
        ]);

        $submission = $this->leads->capture($leadMagnet, $data, $request, $request->user());

        $payload = [
            'data' => [
                'id' => $submission->id,
                'email' => $submission->email,
                'delivery_url' => $leadMagnet->delivery_url,
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return back()->with('success', 'Thanks. Your resource is ready.');
    }
}
