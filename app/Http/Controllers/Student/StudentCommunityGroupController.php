<?php

namespace App\Http\Controllers\Student;

use App\Enums\CommunityContentStatus;
use App\Http\Controllers\Controller;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\User;
use App\Services\Community\CommunityAccessService;
use App\Support\Security\ContentSanitizer;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentCommunityGroupController extends Controller
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
        private readonly CommunityAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $groups = CommunityGroup::query()
            ->with(['course'])
            ->where('status', CommunityContentStatus::Approved->value)
            ->latest()
            ->get()
            ->filter(fn (CommunityGroup $group): bool => $this->access->canAccessGroup($user, $group))
            ->map(fn (CommunityGroup $group): array => $this->groupPayload($group, $user))
            ->values()
            ->all();

        return Inertia::render('student/community/Groups', [
            'groups' => $groups,
        ]);
    }

    public function show(Request $request, CommunityGroup $group): Response
    {
        /** @var User $user */
        $user = $request->user();
        $group->loadMissing(['course', 'members.user']);

        abort_unless($this->access->canAccessGroup($user, $group), 403);

        return Inertia::render('student/community/GroupShow', [
            'group' => [
                ...$this->groupPayload($group, $user),
                'members' => $group->members
                    ->where('status', 'active')
                    ->take(25)
                    ->map(fn (CommunityGroupMember $member): array => [
                        'id' => $member->id,
                        'role' => $member->role,
                        'name' => $member->user?->name,
                        'joined_at' => $this->isoDate($member->getAttribute('joined_at')),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function join(Request $request, CommunityGroup $group): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $group->loadMissing('course');

        abort_unless($this->access->canAccessGroup($user, $group), 403);

        $group->members()->updateOrCreate(
            ['user_id' => $user->id],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now()],
        );

        $group->forceFill(['members_count' => $group->members()->where('status', 'active')->count()])->save();

        return back()->with('success', 'You joined the community group.');
    }

    /**
     * @return array<string, mixed>
     */
    private function groupPayload(CommunityGroup $group, User $user): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'description' => $this->sanitizer->plainText($group->description),
            'visibility' => $this->enumValue($group->getAttribute('visibility')),
            'members_count' => $group->members_count,
            'is_member' => $this->access->isGroupMember($user, $group),
            'url' => route('student.community.groups.show', $group),
            'join_url' => route('student.community.groups.join', $group),
            'course' => $group->course ? [
                'id' => $group->course->id,
                'title' => $group->course->title,
                'url' => route('public.courses.show', $group->course->slug),
            ] : null,
        ];
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
}
