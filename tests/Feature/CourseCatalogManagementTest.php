<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CourseCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_all_catalog_management_pages_are_registered_and_render(): void
    {
        foreach ([
            'course-categories',
            'course-subcategories',
            'course-sections',
            'course-resources',
            'course-faqs',
        ] as $resource) {
            $this->actingAs($this->admin)
                ->get(route("admin.{$resource}.index"))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/Operations')
                    ->where('ordering.enabled', true)
                    ->where('ordering.url', "/admin/{$resource}/reorder"),
                );
        }
    }

    public function test_category_creation_generates_unique_slugs_and_an_audit_log(): void
    {
        $payload = [
            'name' => 'Platform Engineering',
            'description' => 'Production platform skills.',
            'is_active' => true,
            'sort_order' => 4,
            'seo_title' => 'Platform Engineering Courses',
            'seo_description' => 'Learn platform engineering.',
            'seo_image' => 'https://example.test/social/platform.jpg',
            'canonical_url' => 'https://example.test/categories/platform-engineering',
            'schema' => '{"@type":"CollectionPage"}',
        ];

        $this->actingAs($this->admin)
            ->from(route('admin.course-categories.index'))
            ->post(route('admin.course-categories.store'), $payload)
            ->assertRedirect(route('admin.course-categories.index'));

        $this->actingAs($this->admin)
            ->from(route('admin.course-categories.index'))
            ->post(route('admin.course-categories.store'), $payload)
            ->assertRedirect(route('admin.course-categories.index'));

        $this->assertDatabaseHas(CourseCategory::class, [
            'name' => 'Platform Engineering',
            'slug' => 'platform-engineering',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas(CourseCategory::class, [
            'name' => 'Platform Engineering',
            'slug' => 'platform-engineering-2',
        ]);
        $this->assertSame(
            'CollectionPage',
            CourseCategory::query()->where('slug', 'platform-engineering')->firstOrFail()->schema['@type'],
        );
        $this->assertSame(2, AuditLog::query()->where('action', 'admin.course_categories.created')->count());
    }

    public function test_category_listing_supports_search_status_counts_and_pagination(): void
    {
        $target = CourseCategory::factory()->create([
            'name' => 'Cloud Operations',
            'is_active' => true,
        ]);
        Course::factory()->count(2)->create(['course_category_id' => $target->id]);
        CourseSubcategory::factory()->count(2)->for($target, 'category')->create();
        CourseCategory::factory()->count(6)->create(['is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.course-categories.index', ['per_page' => 5]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 5)
                ->where('rows.total', 7),
            );

        $this->actingAs($this->admin)
            ->get(route('admin.course-categories.index', [
                'search' => 'Cloud Operations',
                'status' => 'active',
                'per_page' => 5,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.total', 1)
                ->where('rows.data.0.name', 'Cloud Operations')
                ->where('rows.data.0.courses_count', 2)
                ->where('rows.data.0.subcategories_count', 2),
            );
    }

    public function test_subcategory_requires_a_parent_and_scopes_unique_slugs_to_it(): void
    {
        $firstCategory = CourseCategory::factory()->create();
        $secondCategory = CourseCategory::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.course-subcategories.index'))
            ->post(route('admin.course-subcategories.store'), [
                'name' => 'Laravel',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('course_category_id');

        foreach ([$firstCategory->id, $firstCategory->id, $secondCategory->id] as $categoryId) {
            $this->actingAs($this->admin)
                ->post(route('admin.course-subcategories.store'), [
                    'course_category_id' => $categoryId,
                    'name' => 'Laravel',
                    'is_active' => true,
                    'sort_order' => 0,
                ])
                ->assertRedirect();
        }

        $this->assertDatabaseHas(CourseSubcategory::class, [
            'course_category_id' => $firstCategory->id,
            'slug' => 'laravel',
        ]);
        $this->assertDatabaseHas(CourseSubcategory::class, [
            'course_category_id' => $firstCategory->id,
            'slug' => 'laravel-2',
        ]);
        $this->assertDatabaseHas(CourseSubcategory::class, [
            'course_category_id' => $secondCategory->id,
            'slug' => 'laravel',
        ]);
    }

    public function test_course_form_rejects_a_subcategory_from_another_category(): void
    {
        $selectedCategory = CourseCategory::factory()->create();
        $otherCategory = CourseCategory::factory()->create();
        $invalidSubcategory = CourseSubcategory::factory()->for($otherCategory, 'category')->create();

        $this->actingAs($this->admin)
            ->from(route('admin.courses.index'))
            ->post(route('admin.courses.store'), [
                'title' => 'Invalid Catalog Course',
                'course_category_id' => $selectedCategory->id,
                'course_subcategory_id' => $invalidSubcategory->id,
                'status' => PublishStatus::Draft->value,
                'is_free' => true,
            ])
            ->assertSessionHasErrors('course_subcategory_id');

        $this->assertDatabaseMissing(Course::class, ['title' => 'Invalid Catalog Course']);
    }

    public function test_dependent_subcategory_endpoint_returns_only_the_selected_category(): void
    {
        $category = CourseCategory::factory()->create();
        $otherCategory = CourseCategory::factory()->create();
        $expected = CourseSubcategory::factory()->for($category, 'category')->create();
        CourseSubcategory::factory()->for($otherCategory, 'category')->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.catalog.course-categories.subcategories', $category))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.value', $expected->id)
            ->assertJsonPath('data.0.parentValue', $category->id);
    }

    public function test_authorized_admin_can_quick_create_a_category_from_the_course_form(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.course-categories.quick-store'), [
                'name' => 'Quick Category',
                'description' => 'Created without leaving the course form.',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Quick Category');

        $this->assertDatabaseHas(CourseCategory::class, [
            'name' => 'Quick Category',
            'slug' => 'quick-category',
        ]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'admin.course_categories.created']);
    }

    public function test_view_only_catalog_permission_cannot_quick_create_a_category(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleName::SubAdmin->value);
        $viewer->givePermissionTo('admin.course_categories.view');

        $this->actingAs($viewer)
            ->postJson(route('admin.course-categories.quick-store'), [
                'name' => 'Forbidden Category',
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_categories_support_bulk_activation_and_audited_drag_ordering(): void
    {
        $first = CourseCategory::factory()->create(['is_active' => false, 'sort_order' => 0]);
        $second = CourseCategory::factory()->create(['is_active' => false, 'sort_order' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.course-categories.bulk'), [
                'ids' => [$first->id, $second->id],
                'action' => 'active',
                'value' => 'active',
            ])
            ->assertRedirect();

        $this->assertTrue($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.course-categories.reorder'), [
                'items' => [
                    ['id' => $first->id, 'sort_order' => 20],
                    ['id' => $second->id, 'sort_order' => 10],
                ],
            ])
            ->assertOk();

        $this->assertSame(20, $first->fresh()->sort_order);
        $this->assertSame(10, $second->fresh()->sort_order);
        $this->assertSame(2, AuditLog::query()->where('action', 'admin.course_categories.reordered')->count());
    }

    public function test_category_deletion_is_blocked_until_courses_are_reassigned(): void
    {
        $source = CourseCategory::factory()->create();
        $replacement = CourseCategory::factory()->create();
        $subcategory = CourseSubcategory::factory()->for($source, 'category')->create();
        $course = Course::factory()->create([
            'course_category_id' => $source->id,
            'course_subcategory_id' => $subcategory->id,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.course-categories.index'))
            ->delete(route('admin.course-categories.destroy', $source))
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted($source);

        $this->actingAs($this->admin)
            ->post(route('admin.course-categories.bulk'), [
                'ids' => [$source->id],
                'action' => 'reassign_delete',
                'value' => (string) $replacement->id,
            ])
            ->assertRedirect();

        $this->assertSoftDeleted($source);
        $this->assertSoftDeleted($subcategory);
        $this->assertSame($replacement->id, $course->fresh()->course_category_id);
        $this->assertNull($course->fresh()->course_subcategory_id);
    }

    public function test_catalog_reassignment_requires_the_sensitive_permission(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole(RoleName::SubAdmin->value);
        $operator->givePermissionTo('manage_courses');
        $source = CourseCategory::factory()->create();
        $replacement = CourseCategory::factory()->create();

        $this->actingAs($operator)
            ->post(route('admin.course-categories.bulk'), [
                'ids' => [$source->id],
                'action' => 'reassign_delete',
                'value' => (string) $replacement->id,
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted($source);
    }

    public function test_sections_resources_and_faqs_validate_course_relationships(): void
    {
        $course = Course::factory()->create();
        $otherCourse = Course::factory()->create();
        $lesson = CourseLesson::factory()->for($otherCourse)->create();
        $courseLesson = CourseLesson::factory()->for($course)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.course-sections.store'), [
                'course_id' => $course->id,
                'title' => 'Foundations',
                'description' => 'Start here.',
                'sort_order' => 0,
                'status' => PublishStatus::Draft->value,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->from(route('admin.course-resources.index'))
            ->post(route('admin.course-resources.store'), [
                'course_id' => $course->id,
                'course_lesson_id' => $lesson->id,
                'title' => 'Invalid Resource',
                'type' => 'download',
                'access_level' => 'enrolled',
                'is_downloadable' => true,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertSessionHasErrors('course_lesson_id');

        $this->actingAs($this->admin)
            ->post(route('admin.course-resources.store'), [
                'course_id' => $course->id,
                'course_lesson_id' => $courseLesson->id,
                'title' => 'Architecture Checklist',
                'type' => 'download',
                'access_level' => 'enrolled',
                'file_path' => 'courses/checklists/architecture.pdf',
                'is_downloadable' => true,
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->post(route('admin.course-faqs.store'), [
                'course_id' => $course->id,
                'question' => 'Where do I begin?',
                'answer' => 'Begin with the foundations section.',
                'sort_order' => 0,
                'status' => PublishStatus::Published->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(CourseSection::class, ['course_id' => $course->id, 'slug' => 'foundations']);
        $this->assertDatabaseMissing(CourseResource::class, ['title' => 'Invalid Resource']);
        $this->assertDatabaseHas(CourseResource::class, ['course_id' => $course->id, 'title' => 'Architecture Checklist']);
        $this->assertDatabaseHas(CourseFaq::class, ['course_id' => $course->id, 'question' => 'Where do I begin?']);
    }
}
