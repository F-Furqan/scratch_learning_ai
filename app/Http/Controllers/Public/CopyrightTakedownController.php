<?php

namespace App\Http\Controllers\Public;

use App\Enums\CopyrightTakedownStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\CopyrightTakedownRequest;
use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CopyrightTakedownController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('public/CopyrightTakedown');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'claimant_name' => ['required', 'string', 'max:255'],
            'claimant_email' => ['required', 'email', 'max:255'],
            'claimant_company' => ['nullable', 'string', 'max:255'],
            'rights_owner' => ['required', 'string', 'max:255'],
            'original_work_url' => ['nullable', 'url', 'max:2048'],
            'infringing_url' => ['required', 'url', 'max:2048'],
            'content_title' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:4000'],
            'good_faith_confirmed' => ['accepted'],
            'accuracy_confirmed' => ['accepted'],
            'signature' => ['required', 'string', 'max:255'],
        ]);

        $reportable = $this->resolveReportable((string) $validated['infringing_url']);

        CopyrightTakedownRequest::query()->create([
            ...$validated,
            'reportable_type' => $reportable?->getMorphClass(),
            'reportable_id' => $reportable?->getKey(),
            'status' => CopyrightTakedownStatus::Submitted,
            'good_faith_confirmed' => true,
            'accuracy_confirmed' => true,
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent() ?? '')->limit(2000, '')->toString(),
        ]);

        return back()->with('status', 'copyright-takedown-submitted');
    }

    private function resolveReportable(string $url): ?Model
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($segments) >= 2 && $segments[0] === 'blog') {
            return BlogPost::query()->where('slug', $segments[1])->first();
        }

        if (count($segments) >= 2 && $segments[0] === 'courses') {
            return Course::query()->where('slug', $segments[1])->first();
        }

        return null;
    }
}
