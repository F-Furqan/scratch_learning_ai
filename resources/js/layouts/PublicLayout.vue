<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ChevronDown,
    GraduationCap,
    LayoutDashboard,
    LogIn,
    Mail,
    MapPin,
    Menu,
    Newspaper,
    Phone,
    ShoppingCart,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type SharedProps = {
    name: string;
    auth: {
        user: {
            name: string;
            dashboardUrl: string | null;
        } | null;
    };
};

type NavChild = {
    label: string;
    href: string;
};

type NavItem = {
    label: string;
    href: string;
    icon: unknown;
    children?: NavChild[];
};

const page = usePage<SharedProps>();
const menuOpen = ref(false);

const navItems: NavItem[] = [
    { label: 'Home', href: '/', icon: BookOpen },
    {
        label: 'Courses',
        href: '/courses',
        icon: BookOpen,
        children: [
            { label: 'Course Grid', href: '/courses' },
            { label: 'Course List', href: '/course-list' },
            { label: 'Course Categories', href: '/course-category-3' },
            { label: 'Course Watch', href: '/course-watch' },
            { label: 'Cart', href: '/cart' },
            { label: 'Checkout', href: '/checkout' },
        ],
    },
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: LayoutDashboard,
        children: [
            { label: 'Student Dashboard', href: '/student/dashboard' },
            { label: 'Creator Dashboard', href: '/creator/dashboard' },
            { label: 'Admin Dashboard', href: '/admin/dashboard' },
        ],
    },
    {
        label: 'Pages',
        href: '/about-us',
        icon: UserRound,
        children: [
            { label: 'Instructors', href: '/instructors' },
            { label: 'Authors', href: '/bloggers' },
            { label: 'About Us', href: '/about-us' },
            { label: 'Contact Us', href: '/contact-us' },
            { label: 'Become Instructor', href: '/become-an-instructor' },
            { label: 'Pricing Plan', href: '/pricing-plan' },
            { label: 'FAQ', href: '/faq' },
            { label: 'Testimonials', href: '/testimonials' },
            { label: 'Terms & Conditions', href: '/terms-and-conditions' },
            { label: 'Privacy Policy', href: '/privacy-policy' },
            { label: 'Copyright Takedown', href: '/copyright/takedown' },
        ],
    },
    {
        label: 'Blog',
        href: '/blog',
        icon: Newspaper,
        children: [
            { label: 'Blog Grid', href: '/blog' },
            { label: 'Blog Sidebar', href: '/blog-right-sidebar' },
            { label: 'Blog Masonry', href: '/blog-masonry' },
        ],
    },
];

const user = computed(() => page.props.auth.user);
const currentPath = computed(() => page.url.split('?')[0] || '/');
const mobileItems = computed(() =>
    navItems.flatMap((item) => [item, ...(item.children || [])]),
);

function isActive(item: Pick<NavItem, 'href'>) {
    return item.href === '/'
        ? currentPath.value === '/'
        : currentPath.value === item.href ||
              currentPath.value.startsWith(`${item.href}/`);
}
</script>

<template>
    <div class="sl-public min-h-screen">
        <div class="sl-topbar">
            <div class="sl-container sl-topbar-inner">
                <div class="flex flex-wrap items-center gap-5">
                    <span class="inline-flex items-center gap-2">
                        <MapPin class="h-4 w-4 text-[#ff4667]" />
                        Scratch Learning Online Academy
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <Phone class="h-4 w-4 text-[#ff4667]" />
                        Learner support and business training
                    </span>
                </div>
                <div class="flex items-center gap-5">
                    <Link
                        href="/contact-us"
                        class="inline-flex items-center gap-2 font-bold"
                    >
                        <Mail class="h-4 w-4 text-[#ff4667]" />
                        Contact Us
                    </Link>
                    <Link
                        href="/cart"
                        class="inline-flex items-center gap-2 font-bold"
                    >
                        <ShoppingCart class="h-4 w-4 text-[#ff4667]" />
                        Cart
                    </Link>
                </div>
            </div>
        </div>

        <header class="sl-header">
            <div class="sl-container sl-header-inner">
                <Link href="/" class="sl-logo" aria-label="Scratch Learning">
                    <img
                        src="/brand/scratch-learning-logo.svg"
                        alt="Scratch Learning"
                    />
                </Link>

                <nav class="sl-nav" aria-label="Primary navigation">
                    <div
                        v-for="item in navItems"
                        :key="item.label"
                        class="sl-nav-item"
                    >
                        <Link
                            :href="item.href"
                            class="sl-nav-link"
                            :class="{ 'is-active': isActive(item) }"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.label }}
                            <ChevronDown
                                v-if="item.children"
                                class="h-3.5 w-3.5"
                            />
                        </Link>
                        <div v-if="item.children" class="sl-submenu">
                            <Link
                                v-for="child in item.children"
                                :key="child.href"
                                :href="child.href"
                            >
                                {{ child.label }}
                            </Link>
                        </div>
                    </div>
                </nav>

                <div class="sl-header-actions">
                    <Link
                        v-if="user"
                        :href="user.dashboardUrl || '/dashboard'"
                        class="sl-btn sl-btn-outline"
                    >
                        <LayoutDashboard class="h-4 w-4" />
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link href="/login" class="sl-btn sl-btn-outline">
                            <LogIn class="h-4 w-4" />
                            Login
                        </Link>
                        <Link
                            href="/register/student"
                            class="sl-btn sl-btn-outline"
                        >
                            Student Register
                        </Link>
                        <Link
                            href="/register/creator"
                            class="sl-btn sl-btn-primary"
                        >
                            Creator Register
                        </Link>
                    </template>
                </div>

                <button
                    type="button"
                    class="sl-mobile-toggle"
                    aria-label="Toggle navigation"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="h-5 w-5" />
                    <Menu v-else class="h-5 w-5" />
                </button>
            </div>

            <div class="sl-mobile-panel" :class="{ 'is-open': menuOpen }">
                <nav class="sl-container grid gap-1" aria-label="Mobile menu">
                    <Link
                        v-for="item in mobileItems"
                        :key="`${item.label}-${item.href}`"
                        :href="item.href"
                        @click="menuOpen = false"
                    >
                        <span>{{ item.label }}</span>
                    </Link>
                    <Link
                        :href="
                            user?.dashboardUrl ||
                            (user ? '/dashboard' : '/login')
                        "
                        class="mt-2 bg-[#fff1f4] text-[#ff4667]"
                        @click="menuOpen = false"
                    >
                        <span>{{ user ? 'Dashboard' : 'Login' }}</span>
                    </Link>
                    <Link
                        v-if="!user"
                        href="/register/student"
                        class="bg-[#f8fafc]"
                        @click="menuOpen = false"
                    >
                        <span>Student Register</span>
                    </Link>
                    <Link
                        v-if="!user"
                        href="/register/creator"
                        class="bg-[#fff8ec] text-[#9a5a00]"
                        @click="menuOpen = false"
                    >
                        <span>Creator Register</span>
                    </Link>
                </nav>
            </div>
        </header>

        <main>
            <slot />
        </main>

        <footer class="sl-footer">
            <div class="sl-container sl-footer-grid">
                <div>
                    <Link href="/" class="inline-flex items-center">
                        <img
                            src="/brand/scratch-learning-logo-light.svg"
                            alt="Scratch Learning"
                            class="h-14 w-auto"
                        />
                    </Link>
                    <p class="mt-5 max-w-md">
                        Scratch Learning brings courses, creators, paid access,
                        progress tracking, and business reporting into one LMS
                        built for serious online education.
                    </p>
                </div>
                <div>
                    <h3>Support</h3>
                    <div class="mt-4 grid gap-2">
                        <Link href="/contact-us">Contact Us</Link>
                        <Link href="/faq">FAQ</Link>
                        <Link href="/student/dashboard">Student Dashboard</Link>
                        <Link href="/certificates/verify/demo"
                            >Certificate Verify</Link
                        >
                    </div>
                </div>
                <div>
                    <h3>About</h3>
                    <div class="mt-4 grid gap-2">
                        <Link href="/about-us">About Us</Link>
                        <Link href="/instructors">Instructors</Link>
                        <Link href="/become-an-instructor">Become Creator</Link>
                        <Link href="/register/creator">Creator Register</Link>
                        <Link href="/testimonials">Testimonials</Link>
                    </div>
                </div>
                <div>
                    <h3>Useful Links</h3>
                    <div class="mt-4 grid gap-2">
                        <Link href="/courses">Courses</Link>
                        <Link href="/blog">Blog</Link>
                        <Link href="/pricing-plan">Pricing Plan</Link>
                        <Link href="/terms-and-conditions"
                            >Terms & Conditions</Link
                        >
                        <Link href="/privacy-policy">Privacy Policy</Link>
                        <Link href="/copyright/takedown"
                            >Copyright Takedown</Link
                        >
                    </div>
                </div>
            </div>
            <div class="sl-footer-bottom">
                <div
                    class="sl-container flex flex-wrap items-center justify-between gap-3"
                >
                    <span>
                        Copyright 2026 © Scratch Learning. All rights reserved.
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <GraduationCap class="h-4 w-4 text-[#ffb54a]" />
                        Courses, community, payments, and analytics
                    </span>
                </div>
            </div>
        </footer>
    </div>
</template>
