<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Boxes,
    ShieldCheck,
    UserCheck,
    Users,
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: '/admin/dashboard',
            },
        ],
    },
});

defineProps<{
    stats: {
        totalUsers: number;
        totalStudents: number;
        totalBloggers: number;
        pendingBloggers: number;
    };
    foundation: {
        roles: string[];
        modulesReady: boolean;
        apiVersion: string;
        paymentProvider: string;
    };
}>();
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-normal">
                Admin Dashboard
            </h1>
            <p class="text-sm text-muted-foreground">
                Foundation controls for users, roles, approvals, modules, API,
                and Paddle-ready payments.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Total users</p>
                    <Users class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.totalUsers }}
                </p>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Students</p>
                    <UserCheck class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.totalStudents }}
                </p>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Bloggers</p>
                    <ShieldCheck class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.totalBloggers }}
                </p>
            </section>

            <section class="rounded-lg border bg-card p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-muted-foreground">Pending</p>
                    <BadgeDollarSign class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.pendingBloggers }}
                </p>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
            <section class="rounded-lg border bg-card p-5">
                <div class="flex items-center gap-3">
                    <Boxes class="size-5 text-muted-foreground" />
                    <div>
                        <h2 class="text-base font-semibold">
                            Modular Foundation
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Laravel Modules is installed for future bounded
                            contexts.
                        </p>
                    </div>
                </div>

                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-md border p-3">
                        <dt class="text-muted-foreground">Modules</dt>
                        <dd class="mt-1 font-medium">
                            {{ foundation.modulesReady ? 'Ready' : 'Offline' }}
                        </dd>
                    </div>
                    <div class="rounded-md border p-3">
                        <dt class="text-muted-foreground">API</dt>
                        <dd class="mt-1 font-medium">
                            /api/{{ foundation.apiVersion }}
                        </dd>
                    </div>
                    <div class="rounded-md border p-3">
                        <dt class="text-muted-foreground">Payments</dt>
                        <dd class="mt-1 font-medium">
                            {{ foundation.paymentProvider }}
                        </dd>
                    </div>
                    <div class="rounded-md border p-3">
                        <dt class="text-muted-foreground">Roles</dt>
                        <dd class="mt-1 font-medium">
                            {{ foundation.roles.length }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-lg border bg-card p-5">
                <h2 class="text-base font-semibold">Phase 1 Status</h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span>Roles and permissions</span>
                        <span class="font-medium text-green-700">Ready</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span>Admin access middleware</span>
                        <span class="font-medium text-green-700">Ready</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span>Student and blogger routing</span>
                        <span class="font-medium text-green-700">Ready</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span>Future module boundary</span>
                        <span class="font-medium text-green-700">Ready</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
