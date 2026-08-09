<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\CopyrightTakedownStatus;
use App\Enums\CreatorContentDeletionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\ApprovalHistory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CopyrightTakedownRequest;
use App\Models\Course;
use App\Models\CreatorContentDeletionRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReviewCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_review_center_aggregates_all_admin_review_queues(): void
    {
        $admin = $this->admin();
        $applicant = User::factory()->create(['name' => 'Pending Creator']);
        BloggerProfile::factory()->create([
            'user_id' => $applicant->id,
            'status' => BloggerStatus::Pending,
        ]);

        $blog = BlogPost::factory()->submitted()->create([
            'title' => 'Submitted Review Blog',
        ]);
        $course = Course::factory()->pending()->create([
            'title' => 'Submitted Review Course',
        ]);
        $revision = EditorialRevision::factory()->submitted()->create([
            'editorialable_type' => $blog->getMorphClass(),
            'editorialable_id' => $blog->id,
            'title' => 'Submitted Review Revision',
            'payload' => [
                'title' => 'Updated Review Blog',
                'content' => 'Updated review body.',
            ],
        ]);
        CreatorContentDeletionRequest::factory()->create([
            'requester_id' => $applicant->id,
            'content_type' => $blog->getMorphClass(),
            'content_id' => $blog->id,
            'status' => CreatorContentDeletionStatus::Pending,
            'reason' => 'Remove this article.',
        ]);
        CopyrightTakedownRequest::factory()->create([
            'reportable_type' => $blog->getMorphClass(),
            'reportable_id' => $blog->id,
            'status' => CopyrightTakedownStatus::Submitted,
            'content_title' => 'Reported Review Blog',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.review-center.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/ReviewCenter')
                ->where('summary.sections', 7)
                ->where('summary.total_pending', 7)
                ->where('sections.0.key', 'creator_applications')
                ->where('sections.0.items.0.title', 'Pending Creator')
                ->where('sections.0.items.0.actions.0.label', 'Approve')
                ->where('sections.1.key', 'blog_approvals')
                ->where('sections.1.items.0.title', 'Submitted Review Blog')
                ->where('sections.2.key', 'course_approvals')
                ->where('sections.2.items.0.title', 'Submitted Review Course')
                ->where('sections.3.key', 'revision_approvals')
                ->where('sections.3.items.0.id', $revision->id)
                ->where('sections.3.items.0.diff.0.label', 'Title')
                ->where('sections.3.items.0.diff.0.to', 'Updated Review Blog')
                ->where('sections.4.key', 'delete_requests')
                ->where('sections.4.items.0.actions.0.label', 'Trash')
                ->where('sections.5.key', 'copyright_takedowns')
                ->where('sections.5.items.0.title', 'Reported Review Blog')
                ->where('sections.5.items.0.actions.0.label', 'Reviewing')
                ->where('sections.6.key', 'ownership_videos')
                ->where('sections.6.items.0.title', 'Submitted Review Course'),
            );
    }

    public function test_creator_application_decision_records_review_history(): void
    {
        $admin = $this->admin();
        $applicant = User::factory()->create();
        $profile = BloggerProfile::factory()->create([
            'user_id' => $applicant->id,
            'status' => BloggerStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.review-center.index', absolute: false))
            ->post(route('admin.review-center.creator-applications.approve', $profile), [
                'note' => 'LinkedIn and expertise reviewed.',
                'review_checklist' => [
                    'ownership_rights' => true,
                    'editorial_quality' => true,
                    'seo_metadata' => false,
                    'policy_safety' => true,
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.review-center.index', absolute: false));

        $profile->refresh();

        $this->assertSame(BloggerStatus::Approved, $profile->status);
        $this->assertTrue($profile->is_verified_creator);
        $this->assertSame($admin->id, $profile->reviewed_by);
        $this->assertNotNull($profile->reviewed_at);
        $this->assertTrue($applicant->fresh()->hasRole(RoleName::Blogger->value));
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $profile->getMorphClass(),
            'subject_id' => $profile->id,
            'actor_id' => $admin->id,
            'decision' => 'creator_application_approved',
            'from_status' => BloggerStatus::Pending->value,
            'to_status' => BloggerStatus::Approved->value,
            'note' => 'LinkedIn and expertise reviewed.',
        ]);

        $history = ApprovalHistory::query()
            ->where('subject_type', $profile->getMorphClass())
            ->where('subject_id', $profile->id)
            ->where('decision', 'creator_application_approved')
            ->firstOrFail();

        $this->assertTrue($history->metadata['review_checklist']['ownership_rights']);
        $this->assertFalse($history->metadata['review_checklist']['seo_metadata']);
    }

    public function test_delete_request_decision_records_review_history(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->create();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'status' => PublishStatus::DeleteRequested,
        ]);
        $deletionRequest = CreatorContentDeletionRequest::factory()->create([
            'requester_id' => $creator->id,
            'content_type' => $post->getMorphClass(),
            'content_id' => $post->id,
            'status' => CreatorContentDeletionStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.review-center.index', absolute: false))
            ->post(route('admin.blog-workflow.deletions.reject', $deletionRequest), [
                'note' => 'Keep the article for now.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.review-center.index', absolute: false));

        $deletionRequest->refresh();

        $this->assertSame(CreatorContentDeletionStatus::Rejected, $deletionRequest->status);
        $this->assertSame($admin->id, $deletionRequest->decided_by);
        $this->assertNotNull($deletionRequest->decided_at);
        $this->assertDatabaseHas(ApprovalHistory::class, [
            'subject_type' => $deletionRequest->getMorphClass(),
            'subject_id' => $deletionRequest->id,
            'actor_id' => $admin->id,
            'decision' => 'delete_rejected',
            'from_status' => CreatorContentDeletionStatus::Pending->value,
            'to_status' => CreatorContentDeletionStatus::Rejected->value,
            'note' => 'Keep the article for now.',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
