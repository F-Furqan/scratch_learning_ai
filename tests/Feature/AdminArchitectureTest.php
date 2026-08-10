<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Enums\AdminDomain;
use Modules\Admin\Http\Controllers\CatalogAdminController;
use Modules\Admin\Http\Controllers\CommerceAdminController;
use Modules\Admin\Http\Controllers\CommunityAdminController;
use Modules\Admin\Http\Controllers\EditorialAdminController;
use Modules\Admin\Http\Controllers\GrowthAdminController;
use Modules\Admin\Http\Controllers\LearningAdminController;
use Modules\Admin\Http\Controllers\OperationsAdminController;
use Modules\Admin\Registry\AdminResourceRegistry;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_module_registers_seven_domains_and_all_existing_resources(): void
    {
        $registry = app(AdminResourceRegistry::class);

        $this->assertTrue(Module::isEnabled('Admin'));
        $this->assertCount(7, AdminDomain::cases());
        $this->assertCount(104, $registry->all());
        $this->assertEqualsCanonicalizing(
            AdminDomain::cases(),
            $registry->all()->pluck('domain')->unique()->values()->all(),
        );
    }

    public function test_every_registered_resource_uses_its_domain_controller(): void
    {
        $controllers = [
            AdminDomain::Catalog->value => CatalogAdminController::class,
            AdminDomain::Editorial->value => EditorialAdminController::class,
            AdminDomain::Learning->value => LearningAdminController::class,
            AdminDomain::Commerce->value => CommerceAdminController::class,
            AdminDomain::Community->value => CommunityAdminController::class,
            AdminDomain::Growth->value => GrowthAdminController::class,
            AdminDomain::Operations->value => OperationsAdminController::class,
        ];

        foreach (app(AdminResourceRegistry::class)->all() as $definition) {
            $route = Route::getRoutes()->getByName('admin.'.$definition->routeName.'.index');

            $this->assertNotNull($route, "Missing route for {$definition->key}");
            $this->assertSame(
                $controllers[$definition->domain->value].'@index',
                $route->getActionName(),
                "Incorrect controller for {$definition->key}",
            );
        }
    }

    public function test_resource_view_permission_does_not_allow_mutation(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SubAdmin->value);
        $user->givePermissionTo('admin.courses.view');

        $this->actingAs($user)
            ->get(route('admin.courses.index'))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('admin.courses.store'), [])
            ->assertForbidden();
    }

    public function test_super_admin_receives_grouped_navigation_from_registry(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('adminNavigation', 7)
                ->where('adminNavigation.0.key', 'catalog')
                ->where('adminNavigation.1.key', 'editorial')
                ->where('adminNavigation.2.key', 'learning')
                ->where('adminNavigation.3.key', 'commerce')
                ->where('adminNavigation.4.key', 'community')
                ->where('adminNavigation.5.key', 'growth')
                ->where('adminNavigation.6.key', 'operations'),
            );
    }

    public function test_resource_and_sensitive_permissions_are_seeded(): void
    {
        $registry = app(AdminResourceRegistry::class);

        $this->assertSame(
            count($registry->permissionNames()),
            Permission::query()->whereIn('name', $registry->permissionNames())->count(),
        );

        $this->assertDatabaseHas(Permission::class, ['name' => 'admin.content.publish']);
        $this->assertDatabaseHas(Permission::class, ['name' => 'admin.content.force_delete']);
        $this->assertDatabaseHas(Permission::class, ['name' => 'admin.payments.reconcile']);
        $this->assertDatabaseHas(Permission::class, ['name' => 'admin.roles.assign']);
    }

    public function test_domain_query_filter_preserves_search_and_status_behavior(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        Course::factory()->create([
            'title' => 'Architecture Search Target',
            'status' => PublishStatus::Draft,
        ]);
        Course::factory()->create([
            'title' => 'Unrelated Published Course',
            'status' => PublishStatus::Published,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.courses.index', [
                'search' => 'Architecture Search',
                'status' => PublishStatus::Draft->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.title', 'Architecture Search Target'),
            );
    }

    public function test_permission_catalog_sync_preserves_custom_sub_admin_assignments(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SubAdmin->value);
        $user->givePermissionTo('manage_courses');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue($user->fresh()->can('manage_courses'));
    }
}
