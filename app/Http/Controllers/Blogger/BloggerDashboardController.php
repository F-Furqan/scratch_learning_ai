<?php

namespace App\Http\Controllers\Blogger;

use App\Http\Controllers\Controller;
use App\Models\EditorialRevision;
use App\Models\RevenueShareRule;
use App\Models\User;
use App\Services\Creators\CreatorAgreementService;
use App\Services\Creators\CreatorAnalyticsService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BloggerDashboardController extends Controller
{
    public function __invoke(Request $request, CreatorAnalyticsService $analytics, CreatorAgreementService $agreements): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['bloggerProfile', 'instructorProfile', 'authorBadges']);

        $revisions = EditorialRevision::query()
            ->where('author_id', $user->id)
            ->with(['comments'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (EditorialRevision $revision): array => [
                'id' => $revision->id,
                'title' => $revision->title,
                'status' => $this->enumValue($revision->getAttribute('status')),
                'comments_count' => $revision->comments->count(),
                'submitted_at' => $this->dateString($revision->getAttribute('submitted_at')),
                'scheduled_at' => $this->dateString($revision->getAttribute('scheduled_at')),
            ]);

        $revenueRules = RevenueShareRule::query()
            ->where('user_id', $user->id)
            ->with('course')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (RevenueShareRule $rule): array => [
                'id' => $rule->id,
                'course_title' => $rule->course?->title,
                'type' => $this->enumValue($rule->getAttribute('type')),
                'status' => $this->enumValue($rule->getAttribute('status')),
                'share_percent' => (float) $rule->share_percent,
                'currency' => $rule->currency,
            ]);

        return Inertia::render('blogger/Dashboard', [
            'profile' => [
                'blogger_status' => $this->enumValue($user->bloggerProfile?->getAttribute('status')),
                'instructor_status' => $this->enumValue($user->instructorProfile?->getAttribute('status')),
                'instructor_display_name' => $user->instructorProfile?->display_name,
                'is_verified_expert' => (bool) $user->instructorProfile?->is_verified_expert
                    || $user->authorBadges->contains(fn ($badge): bool => (bool) $badge->marks_verified_expert),
                'badges' => $user->authorBadges
                    ->where('is_active', true)
                    ->map(fn ($badge): array => [
                        'id' => $badge->id,
                        'name' => $badge->name,
                        'color' => $badge->color,
                    ])
                    ->values()
                    ->all(),
            ],
            'agreement' => [
                'version' => $agreements->currentVersion(),
                'accepted' => $agreements->hasAccepted($user),
                'url' => route('creator.agreement.show', absolute: false),
            ],
            'analytics' => $analytics->summaryFor($user),
            'revisions' => $revisions,
            'revenue_rules' => $revenueRules,
        ]);
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : (is_string($value) ? $value : null);
    }
}
