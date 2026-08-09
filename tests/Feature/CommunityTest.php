<?php

namespace Tests\Feature;

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityReportStatus;
use App\Enums\CommunityVisibility;
use App\Enums\CoursePurchaseStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlockedWord;
use App\Models\CommunityGroup;
use App\Models\CommunityReputationScore;
use App\Models\ContentReport;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CoursePurchase;
use App\Models\CourseQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\ModerationQueueItem;
use App\Models\ReputationEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_lesson_question_with_blocked_word_enters_spam_moderation(): void
    {
        $student = $this->student();
        $lesson = $this->freeLesson();

        BlockedWord::factory()->create([
            'word' => 'buy followers',
            'severity' => 3,
            'is_active' => true,
        ]);

        $questionId = $this->actingAs($student)
            ->postJson(route('student.lessons.questions.store', $lesson->id), [
                'title' => 'Can I share a vendor?',
                'body' => 'This message says buy followers in the lesson chat.',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas(CourseQuestion::class, [
            'id' => $questionId,
            'status' => PublishStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas(ModerationQueueItem::class, [
            'subject_type' => (new CourseQuestion)->getMorphClass(),
            'subject_id' => $questionId,
            'status' => CommunityContentStatus::Spam->value,
            'spam_score' => 3,
        ]);
    }

    public function test_accepting_lesson_answer_awards_reputation(): void
    {
        $asker = $this->student();
        $answerer = $this->student();
        $lesson = $this->freeLesson();

        $question = CourseQuestion::factory()->create([
            'course_id' => $lesson->course_id,
            'course_lesson_id' => $lesson->id,
            'user_id' => $asker->id,
            'status' => PublishStatus::Published,
        ]);

        $answer = LessonQuestionAnswer::factory()->approved()->create([
            'course_question_id' => $question->id,
            'user_id' => $answerer->id,
        ]);

        $this->actingAs($asker)
            ->postJson(route('student.questions.answers.accept', [$question->id, $answer->id]))
            ->assertOk();

        $question->refresh();
        $answer->refresh();

        $this->assertSame($answer->id, $question->accepted_answer_id);
        $this->assertNotNull($answer->accepted_at);
        $this->assertDatabaseHas(CommunityReputationScore::class, [
            'user_id' => $answerer->id,
            'points' => 15,
            'level' => 'newcomer',
        ]);
        $this->assertDatabaseHas(ReputationEvent::class, [
            'user_id' => $answerer->id,
            'actor_id' => $asker->id,
            'points' => 15,
        ]);
    }

    public function test_paid_member_groups_require_course_entitlement(): void
    {
        $student = $this->student();
        $course = Course::factory()->published()->create([
            'is_free' => false,
            'price' => 9900,
        ]);
        $group = CommunityGroup::factory()->create([
            'course_id' => $course->id,
            'visibility' => CommunityVisibility::PaidMembers,
            'status' => CommunityContentStatus::Approved,
            'requires_paid_access' => true,
        ]);

        $this->actingAs($student)
            ->get(route('student.community.groups.show', $group))
            ->assertForbidden();

        CoursePurchase::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => CoursePurchaseStatus::Active,
        ]);

        $this->actingAs($student)
            ->get(route('student.community.groups.show', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/community/GroupShow')
                ->where('group.id', $group->id),
            );
    }

    public function test_reactions_and_reports_create_engagement_and_review_work(): void
    {
        $answerer = $this->student();
        $voter = $this->student();
        $lesson = $this->freeLesson();
        $question = CourseQuestion::factory()->create([
            'course_id' => $lesson->course_id,
            'course_lesson_id' => $lesson->id,
            'status' => PublishStatus::Published,
        ]);
        $answer = LessonQuestionAnswer::factory()->approved()->create([
            'course_question_id' => $question->id,
            'user_id' => $answerer->id,
        ]);

        $this->actingAs($voter)
            ->postJson(route('student.community.answers.reactions.store', $answer), [
                'type' => 'upvote',
            ])
            ->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.upvotes_count', 1);

        $this->assertDatabaseHas(CommunityReputationScore::class, [
            'user_id' => $answerer->id,
            'points' => 1,
        ]);

        $this->actingAs($voter)
            ->postJson(route('student.community.answers.reports.store', $answer), [
                'reason' => 'needs_review',
                'details' => 'This answer looks suspicious.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas(ContentReport::class, [
            'reporter_id' => $voter->id,
            'reportable_type' => $answer->getMorphClass(),
            'reportable_id' => $answer->id,
            'status' => CommunityReportStatus::Open->value,
        ]);
        $this->assertDatabaseHas(ModerationQueueItem::class, [
            'subject_type' => $answer->getMorphClass(),
            'subject_id' => $answer->id,
            'reason' => 'reported: needs_review',
        ]);
    }

    public function test_admin_bulk_approval_decides_moderation_queue_and_publishes_question(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);
        $lesson = $this->freeLesson();
        $question = CourseQuestion::factory()->create([
            'course_id' => $lesson->course_id,
            'course_lesson_id' => $lesson->id,
            'status' => PublishStatus::Pending,
        ]);
        $item = ModerationQueueItem::factory()->create([
            'subject_type' => $question->getMorphClass(),
            'subject_id' => $question->id,
            'status' => CommunityContentStatus::Pending,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.community.moderation.bulk'), [
                'ids' => [$item->id],
                'action' => 'status',
                'value' => CommunityContentStatus::Approved->value,
                'note' => 'Looks good.',
            ])
            ->assertRedirect();

        $question->refresh();
        $item->refresh();

        $this->assertSame(PublishStatus::Published, $question->status);
        $this->assertSame(CommunityContentStatus::Approved, $item->status);
        $this->assertSame('Looks good.', $item->resolution_note);
    }

    private function student(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Student->value);
        $user->studentProfile()->firstOrCreate();

        return $user;
    }

    private function freeLesson(): CourseLesson
    {
        $course = Course::factory()->free()->published()->create();

        return CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'allow_questions' => true,
            'is_paid' => false,
        ]);
    }
}
