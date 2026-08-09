<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BookOpen,
    ChartNoAxesColumnIncreasing,
    FileText,
    GraduationCap,
    Image,
    LayoutDashboard,
    Library,
    ListTree,
    Newspaper,
    PenLine,
    Settings,
    ShieldCheck,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const user = computed(() => page.props.auth.user);

const hasPermission = (permission: string) =>
    user.value?.permissions.includes(permission) ?? false;

const hasRole = (role: string) => user.value?.roles.includes(role) ?? false;

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: user.value?.dashboardUrl || dashboard(),
            icon: LayoutDashboard,
        },
    ];

    if (user.value?.canAccessAdmin) {
        items.push({
            title: 'Admin',
            href: '/admin/dashboard',
            icon: ShieldCheck,
        });
    }

    if (
        hasPermission('approve_bloggers') ||
        hasPermission('manage_blogs') ||
        hasPermission('manage_courses') ||
        hasPermission('manage_cms')
    ) {
        items.push({
            title: 'Review Center',
            href: '/admin/review-center',
            icon: ShieldCheck,
        });
    }

    if (hasRole('student')) {
        items.push({
            title: 'My Learning',
            href: '/student/dashboard',
            icon: GraduationCap,
        });
    }

    if (hasRole('blogger')) {
        items.push({
            title: 'Creator',
            href: '/creator/dashboard',
            icon: PenLine,
        });
        items.push({
            title: 'Creator Profile',
            href: '/creator/profile',
            icon: Users,
        });
        items.push({
            title: 'Guidelines',
            href: '/creator/guidelines',
            icon: FileText,
        });
        items.push({
            title: 'Creator Blogs',
            href: '/creator/blogs',
            icon: Newspaper,
        });
        items.push({
            title: 'Creator Courses',
            href: '/creator/courses',
            icon: Library,
        });
    }

    if (hasPermission('manage_users')) {
        items.push({ title: 'Users', href: '/admin/users', icon: Users });
    }

    if (hasPermission('manage_roles') || hasPermission('manage_permissions')) {
        items.push({
            title: 'Roles',
            href: '/admin/roles',
            icon: ShieldCheck,
        });
    }

    if (hasPermission('manage_permissions')) {
        items.push({
            title: 'Permissions',
            href: '/admin/permissions',
            icon: ShieldCheck,
        });
    }

    if (hasPermission('approve_bloggers')) {
        items.push({
            title: 'Bloggers',
            href: '/admin/bloggers',
            icon: PenLine,
        });
    }

    if (hasPermission('manage_courses')) {
        items.push({
            title: 'Courses',
            href: '/admin/courses',
            icon: Library,
        });
    }

    if (hasPermission('manage_lessons') || hasPermission('manage_courses')) {
        items.push({
            title: 'Lessons',
            href: '/admin/lessons',
            icon: BookOpen,
        });
    }

    if (hasPermission('manage_blogs')) {
        items.push({
            title: 'Blogs',
            href: '/admin/blogs',
            icon: Newspaper,
        });
    }

    if (hasPermission('manage_courses') || hasPermission('manage_blogs')) {
        items.push({
            title: 'Trash',
            href: '/admin/trash',
            icon: Trash2,
        });
    }

    if (hasPermission('manage_cms')) {
        items.push({ title: 'CMS', href: '/admin/cms', icon: FileText });
        items.push({
            title: 'Home Hero',
            href: '/admin/cms/home-hero',
            icon: Image,
        });
        items.push({
            title: 'Hero Slides',
            href: '/admin/cms/home-hero-slides',
            icon: Image,
        });
        items.push({
            title: 'Homepage Sections',
            href: '/admin/cms/homepage-sections',
            icon: ListTree,
        });
        items.push({ title: 'Menus', href: '/admin/menus', icon: ListTree });
    }

    if (hasPermission('manage_media')) {
        items.push({ title: 'Media', href: '/admin/media', icon: Image });
    }

    if (hasPermission('manage_ads')) {
        items.push({
            title: 'Ad Zones',
            href: '/admin/ads/zones',
            icon: BadgeDollarSign,
        });
        items.push({
            title: 'Campaigns',
            href: '/admin/ads/campaigns',
            icon: BadgeDollarSign,
        });
        items.push({
            title: 'Creatives',
            href: '/admin/ads/creatives',
            icon: Image,
        });
        items.push({
            title: 'Advertisers',
            href: '/admin/ads/advertiser-requests',
            icon: Users,
        });
    }

    if (hasPermission('view_reports')) {
        items.push({
            title: 'Reports',
            href: '/admin/reports',
            icon: ChartNoAxesColumnIncreasing,
        });
    }

    if (hasPermission('manage_payments')) {
        items.push({
            title: 'Products',
            href: '/admin/payments/products',
            icon: BadgeDollarSign,
        });
        items.push({
            title: 'Plans',
            href: '/admin/payments/prices',
            icon: BadgeDollarSign,
        });
        items.push({
            title: 'Orders',
            href: '/admin/payments/orders',
            icon: ChartNoAxesColumnIncreasing,
        });
        items.push({
            title: 'Subscriptions',
            href: '/admin/payments/subscriptions',
            icon: Users,
        });
        items.push({
            title: 'Teams',
            href: '/admin/payments/teams',
            icon: Users,
        });
    }

    if (hasPermission('manage_settings')) {
        items.push({
            title: 'Settings',
            href: '/admin/settings',
            icon: Settings,
        });
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
