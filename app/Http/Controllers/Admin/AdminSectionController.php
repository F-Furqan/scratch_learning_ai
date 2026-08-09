<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSectionController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string}>
     */
    private const SECTIONS = [
        'users' => [
            'title' => 'User Management',
            'description' => 'Manage students, bloggers, sub admins, account status, and future purchases.',
        ],
        'roles' => [
            'title' => 'Roles & Permissions',
            'description' => 'Assign sub-admin permissions while protecting the super admin role.',
        ],
        'bloggers' => [
            'title' => 'Blogger Management',
            'description' => 'Review blogger applications, approvals, suspensions, and editorial access.',
        ],
        'courses' => [
            'title' => 'Course Management',
            'description' => 'The future home for categories, courses, sections, lessons, and pricing.',
        ],
        'blogs' => [
            'title' => 'Blog Management',
            'description' => 'Manage admin posts, blogger posts, categories, tags, approvals, and SEO.',
        ],
        'cms' => [
            'title' => 'CMS Management',
            'description' => 'Manage pages, menus, homepage sections, banners, footer links, and settings.',
        ],
        'media' => [
            'title' => 'Media Library',
            'description' => 'Upload, organize, search, and reuse images, video, documents, and gallery assets.',
        ],
        'settings' => [
            'title' => 'Platform Settings',
            'description' => 'Configure SEO defaults, email settings, payment settings, and operational flags.',
        ],
    ];

    public function __invoke(Request $request): Response
    {
        $section = (string) $request->route('section');
        $content = self::SECTIONS[$section] ?? [
            'title' => 'Admin Section',
            'description' => 'This admin section is ready for the next implementation phase.',
        ];

        return Inertia::render('admin/Placeholder', [
            'title' => $content['title'],
            'description' => $content['description'],
        ]);
    }
}
