<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LockKeyhole, Users } from '@lucide/vue';

type GroupPayload = {
    id: number;
    name: string;
    description: string | null;
    visibility: string;
    members_count: number;
    is_member: boolean;
    url: string;
    course: { title: string; url: string } | null;
};

defineProps<{
    groups: GroupPayload[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Community Groups',
                href: '/student/community/groups',
            },
        ],
    },
});
</script>

<template>
    <Head title="Community Groups" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">
                Community Groups
            </h1>
            <p class="text-sm text-muted-foreground">
                Private spaces unlocked by paid courses, subscriptions, or team
                seats.
            </p>
        </div>

        <div v-if="groups.length" class="grid gap-4 lg:grid-cols-2">
            <Link
                v-for="group in groups"
                :key="group.id"
                :href="group.url"
                class="rounded-lg border bg-card p-5 transition hover:border-slate-400"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-semibold">{{ group.name }}</h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ group.description }}
                        </p>
                    </div>
                    <LockKeyhole class="h-5 w-5 text-amber-600" />
                </div>
                <div
                    class="mt-5 flex flex-wrap items-center gap-4 text-sm text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <Users class="h-4 w-4" />
                        {{ group.members_count }} members
                    </span>
                    <span v-if="group.is_member">Joined</span>
                    <span v-if="group.course">{{ group.course.title }}</span>
                </div>
            </Link>
        </div>
        <div
            v-else
            class="rounded-lg border border-dashed p-8 text-muted-foreground"
        >
            No paid-member groups are available for your account yet.
        </div>
    </div>
</template>
