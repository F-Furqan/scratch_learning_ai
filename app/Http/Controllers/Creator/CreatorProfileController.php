<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreatorProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('bloggerProfile');

        return Inertia::render('creator/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->bloggerProfile?->phone,
                'bio' => $user->bloggerProfile?->bio,
                'expertise' => $user->bloggerProfile?->expertise,
                'linkedin_url' => $user->bloggerProfile?->linkedin_url,
                'website_url' => $user->bloggerProfile?->website_url,
                'application_reason' => $user->bloggerProfile?->application_reason,
                'status' => $user->bloggerProfile?->status?->value,
                'admin_notes' => $user->bloggerProfile?->admin_notes,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $user->forceFill(['name' => $validated['name']])->save();

        $user->bloggerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $validated['phone'] ?? null,
                'bio' => $validated['bio'] ?? null,
                'expertise' => $validated['expertise'] ?? null,
                'linkedin_url' => $validated['linkedin_url'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
            ],
        );

        return back()->with('status', 'creator-profile-updated');
    }
}
