<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BloggerStatus;
use App\Http\Controllers\Controller;
use App\Models\BloggerProfile;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Nwidart\Modules\Facades\Module;

class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalUsers' => User::count(),
                'totalStudents' => User::role('student')->count(),
                'totalBloggers' => User::role('blogger')->count(),
                'pendingBloggers' => BloggerProfile::query()
                    ->where('status', BloggerStatus::Pending->value)
                    ->count(),
            ],
            'foundation' => [
                'roles' => ['super_admin', 'sub_admin', 'blogger', 'student'],
                'modulesReady' => class_exists(Module::class),
                'apiVersion' => 'v1',
                'paymentProvider' => 'Paddle',
            ],
        ]);
    }
}
