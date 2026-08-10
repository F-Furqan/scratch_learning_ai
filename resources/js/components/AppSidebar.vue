<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    FileText,
    GraduationCap,
    LayoutDashboard,
    Library,
    Newspaper,
    PenLine,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavAdminGroups from '@/components/NavAdminGroups.vue';
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
import type { AdminNavigationGroup, NavItem } from '@/types';

const page = usePage();

const user = computed(() => page.props.auth.user);
const adminNavigation = computed<AdminNavigationGroup[]>(
    () =>
        (page.props.adminNavigation as AdminNavigationGroup[] | undefined) ??
        [],
);

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
        hasPermission('manage_cms') ||
        hasPermission('admin.content.publish')
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
        items.push(
            {
                title: 'Creator',
                href: '/creator/dashboard',
                icon: PenLine,
            },
            {
                title: 'Creator Profile',
                href: '/creator/profile',
                icon: Users,
            },
            {
                title: 'Guidelines',
                href: '/creator/guidelines',
                icon: FileText,
            },
            {
                title: 'Creator Blogs',
                href: '/creator/blogs',
                icon: Newspaper,
            },
            {
                title: 'Creator Courses',
                href: '/creator/courses',
                icon: Library,
            },
        );
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
            <NavAdminGroups
                v-if="adminNavigation.length > 0"
                :groups="adminNavigation"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
