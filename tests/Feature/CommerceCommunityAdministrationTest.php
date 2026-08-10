<?php

namespace Tests\Feature;

use App\Enums\CommunityContentStatus;
use App\Enums\CoursePurchaseStatus;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use App\Enums\PaymentOrderStatus;
use App\Enums\PublishStatus;
use App\Enums\ReputationEventType;
use App\Enums\RoleName;
use App\Jobs\ProcessPaddleWebhookEventJob;
use App\Jobs\ReconcilePaymentRecordJob;
use App\Models\CommunityGroupMember;
use App\Models\CommunityReputationScore;
use App\Models\Course;
use App\Models\CoursePurchase;
use App\Models\CourseQuestion;
use App\Models\LessonQuestionAnswer;
use App\Models\ModerationQueueItem;
use App\Models\PaymentAuditLog;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use App\Models\ReputationEvent;
use App\Models\User;
use App\Services\Payments\CourseAccessService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Registry\AdminResourceRegistry;
use Modules\Admin\Services\CommerceOperationsAdminService;
use Modules\Admin\Services\CommunityOperationsAdminService;
use Tests\TestCase;

class CommerceCommunityAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
        Config::set('payments.paddle.api_key', 'pdl_sdbx_phase6');
        Config::set('payments.paddle.environment', 'sandbox');
    }

    public function test_phase_six_resources_are_registered_as_read_only_exportable_screens(): void
    {
        $registry = app(AdminResourceRegistry::class);
        $resources = [...CommerceOperationsAdminService::RESOURCES, ...CommunityOperationsAdminService::RESOURCES];

        $this->assertCount(104, $registry->all());

        foreach ($resources as $resource) {
            $definition = $registry->get($resource);

            $this->actingAs($this->admin)
                ->get(route('admin.'.$definition->routeName.'.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('resource', $resource)
                    ->where('canCreate', false)
                    ->where('canEdit', false)
                    ->where('canDelete', false)
                    ->where('exportUrl', route('admin.operational-records.export', $resource, false)));
        }

        foreach ([
            'admin.payments.refund',
            'admin.payments.revoke_access',
            'admin.records.export',
            'admin.community.moderate',
            'admin.community.accept_answers',
            'admin.community.adjust_reputation',
            'admin.community.manage_members',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'web']);
        }
    }

    public function test_operational_records_reject_generic_crud_and_non_admin_access(): void
    {
        $registry = app(AdminResourceRegistry::class);

        foreach ([...CommerceOperationsAdminService::RESOURCES, ...CommunityOperationsAdminService::RESOURCES] as $resource) {
            $definition = $registry->get($resource);

            $this->actingAs($this->admin)
                ->post(route('admin.'.$definition->routeName.'.store'), [])
                ->assertStatus(405);
        }

        $student = User::factory()->create();
        $event = PaymentWebhookEvent::factory()->create(['status' => 'failed']);

        $this->actingAs($student)
            ->patch(route('admin.operational-records.update', ['type' => 'webhook-event', 'id' => $event->id]), [
                'action' => 'retry',
                'reason' => 'Retry the failed event.',
            ])
            ->assertForbidden();
    }

    public function test_failed_webhooks_and_reconciliation_records_queue_controlled_retries(): void
    {
        Queue::fake();
        $event = PaymentWebhookEvent::factory()->create([
            'status' => 'failed',
            'last_error' => 'Temporary Paddle timeout.',
        ]);
        $reconciliation = PaymentReconciliationRecord::query()->create([
            'provider' => 'paddle',
            'event_id' => $event->event_id,
            'record_type' => 'transaction',
            'status' => 'failed',
            'notes' => 'Initial processing failed.',
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'webhook-event', 'id' => $event->id]), [
                'action' => 'retry',
                'reason' => 'Paddle is available again.',
            ])
            ->assertRedirect();

        $event->refresh();
        $this->assertSame('queued', $event->status);
        $this->assertNull($event->last_error);
        Queue::assertPushed(ProcessPaddleWebhookEventJob::class, fn (ProcessPaddleWebhookEventJob $job): bool => $job->eventId === $event->id && $job->queue === 'payments');
        $this->assertDatabaseHas(PaymentAuditLog::class, [
            'action' => 'admin.payment.retry',
            'auditable_id' => $event->id,
            'actor_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'reconciliation', 'id' => $reconciliation->id]), [
                'action' => 'reconcile',
                'reason' => 'Rebuild the local state from the source event.',
            ])
            ->assertRedirect();

        Queue::assertPushed(ReconcilePaymentRecordJob::class, fn (ReconcilePaymentRecordJob $job): bool => $job->recordId === $reconciliation->id && $job->queue === 'payments');
    }

    public function test_purchase_and_entitlement_actions_change_access_together(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create(['is_free' => false]);
        $order = PaymentOrder::factory()->create(['user_id' => $student->id]);
        $purchase = CoursePurchase::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'payment_order_id' => $order->id,
        ]);
        $entitlement = PaymentEntitlement::factory()->create([
            'user_id' => $student->id,
            'entitlementable_type' => $course->getMorphClass(),
            'entitlementable_id' => $course->id,
            'payment_order_id' => $order->id,
            'type' => PaymentEntitlementType::Course,
        ]);

        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($student, $course));

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'course-purchase', 'id' => $purchase->id]), [
                'action' => 'revoke',
                'reason' => 'Chargeback review requires access removal.',
            ])
            ->assertRedirect();

        $this->assertSame(CoursePurchaseStatus::Revoked, $purchase->refresh()->status);
        $this->assertSame(PaymentEntitlementStatus::Revoked, $entitlement->refresh()->status);
        $this->assertFalse(app(CourseAccessService::class)->canAccessCourse($student, $course));

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'entitlement', 'id' => $entitlement->id]), [
                'action' => 'restore',
                'reason' => 'Payment review completed successfully.',
            ])
            ->assertRedirect();

        $this->assertSame(CoursePurchaseStatus::Active, $purchase->refresh()->status);
        $this->assertSame(PaymentEntitlementStatus::Active, $entitlement->refresh()->status);
        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($student, $course));
    }

    public function test_approved_paddle_refund_records_adjustment_and_revokes_access_once(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create(['is_free' => false]);
        $order = PaymentOrder::factory()->create([
            'user_id' => $student->id,
            'status' => PaymentOrderStatus::Completed,
            'paddle_transaction_id' => 'txn_phase6_refund',
        ]);
        CoursePurchase::factory()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'payment_order_id' => $order->id,
        ]);
        PaymentEntitlement::factory()->create([
            'user_id' => $student->id,
            'entitlementable_type' => $course->getMorphClass(),
            'entitlementable_id' => $course->id,
            'payment_order_id' => $order->id,
            'type' => PaymentEntitlementType::Course,
        ]);

        Http::fake([
            'https://sandbox-api.paddle.com/adjustments' => Http::response([
                'data' => [
                    'id' => 'adj_phase6_refund',
                    'status' => 'approved',
                    'transaction_id' => $order->paddle_transaction_id,
                ],
            ], 201),
        ]);

        $url = route('admin.operational-records.update', ['type' => 'payment-order', 'id' => $order->id]);
        $payload = ['action' => 'refund', 'reason' => 'Customer requested a full refund.'];

        $this->actingAs($this->admin)->patch($url, $payload)->assertRedirect();

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/adjustments'
            && $request->data()['action'] === 'refund'
            && $request->data()['type'] === 'full'
            && $request->data()['transaction_id'] === $order->paddle_transaction_id);
        $this->assertSame(PaymentOrderStatus::Refunded, $order->refresh()->status);
        $this->assertDatabaseHas(CoursePurchase::class, ['payment_order_id' => $order->id, 'status' => CoursePurchaseStatus::Refunded->value]);
        $this->assertDatabaseHas(PaymentEntitlement::class, ['payment_order_id' => $order->id, 'status' => PaymentEntitlementStatus::Revoked->value]);
        $this->assertDatabaseHas(PaymentReconciliationRecord::class, ['event_id' => 'adj_phase6_refund', 'record_type' => 'refund', 'status' => 'approved']);
        $this->assertDatabaseHas(PaymentAuditLog::class, ['action' => 'payment.order.refund_requested', 'auditable_id' => $order->id]);

        $this->actingAs($this->admin)->patch($url, $payload)->assertSessionHasErrors('action');
        Http::assertSentCount(1);
    }

    public function test_moderation_acceptance_reputation_and_membership_actions_are_audited(): void
    {
        $question = CourseQuestion::factory()->create();
        $answer = LessonQuestionAnswer::factory()->create(['course_question_id' => $question->id]);

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'answer', 'id' => $answer->id]), [
                'action' => 'accept',
                'reason' => 'This directly resolves the lesson question.',
            ])
            ->assertRedirect();

        $this->assertSame($answer->id, $question->refresh()->accepted_answer_id);
        $this->assertSame(CommunityContentStatus::Approved, $answer->refresh()->status);
        $this->assertDatabaseHas(ReputationEvent::class, ['user_id' => $answer->user_id, 'type' => ReputationEventType::AnswerAccepted->value, 'points' => 15]);

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'question', 'id' => $question->id]), [
                'action' => 'spam',
                'reason' => 'The question contains repeated promotional spam.',
            ])
            ->assertRedirect();

        $this->assertSame(PublishStatus::Rejected, $question->refresh()->status);
        $this->assertDatabaseHas(ModerationQueueItem::class, [
            'subject_type' => $question->getMorphClass(),
            'subject_id' => $question->id,
            'status' => CommunityContentStatus::Spam->value,
            'assigned_to' => $this->admin->id,
        ]);

        $score = CommunityReputationScore::query()->firstOrCreate(
            ['user_id' => $answer->user_id],
            ['points' => 15, 'level' => 'newcomer'],
        );
        $beforePoints = $score->points;

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'reputation-score', 'id' => $score->id]), [
                'action' => 'adjust',
                'points' => 25,
                'reason' => 'Manual credit for a verified expert contribution.',
            ])
            ->assertRedirect();

        $this->assertSame($beforePoints + 25, $score->refresh()->points);
        $this->assertDatabaseHas(ReputationEvent::class, ['user_id' => $score->user_id, 'type' => ReputationEventType::AdminAdjustment->value, 'points' => 25]);

        $member = CommunityGroupMember::factory()->create();
        $member->group->forceFill(['members_count' => 1])->save();

        $this->actingAs($this->admin)
            ->patch(route('admin.operational-records.update', ['type' => 'group-member', 'id' => $member->id]), [
                'action' => 'remove',
                'reason' => 'Membership access no longer applies.',
            ])
            ->assertRedirect();

        $this->assertSame('removed', $member->refresh()->status);
        $this->assertSame(0, $member->group->refresh()->members_count);
    }

    public function test_csv_export_respects_filters_and_prevents_spreadsheet_formulas(): void
    {
        PaymentWebhookEvent::factory()->create([
            'event_id' => 'evt_formula',
            'event_type' => 'transaction.payment_failed',
            'status' => 'failed',
            'last_error' => '=HYPERLINK("https://example.test")',
        ]);
        PaymentWebhookEvent::factory()->create([
            'event_id' => 'evt_processed',
            'status' => 'processed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.operational-records.export', [
            'resource' => 'payment_webhook_events',
            'status' => 'failed',
        ]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('evt_formula', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('evt_processed', $csv);
    }
}
