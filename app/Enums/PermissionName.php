<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageUsers = 'manage_users';
    case ManageRoles = 'manage_roles';
    case ManagePermissions = 'manage_permissions';
    case ManageCms = 'manage_cms';
    case ManageCourses = 'manage_courses';
    case ManageLessons = 'manage_lessons';
    case ManageBlogs = 'manage_blogs';
    case ApproveBloggers = 'approve_bloggers';
    case ManageComments = 'manage_comments';
    case ManageFaqs = 'manage_faqs';
    case ManageMedia = 'manage_media';
    case ManageAds = 'manage_ads';
    case ManagePayments = 'manage_payments';
    case ManageSubscriptions = 'manage_subscriptions';
    case ManageEmailSettings = 'manage_email_settings';
    case ManageSeoSettings = 'manage_seo_settings';
    case ManageLiveCourses = 'manage_live_courses';
    case ViewReports = 'view_reports';
    case ManageSettings = 'manage_settings';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }
}
