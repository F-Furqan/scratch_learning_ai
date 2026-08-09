<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, LockKeyhole, UserPlus, Users } from '@lucide/vue';

type MemberPayload = {
    id: number;
    role: string;
    name: string | null;
    joined_at: string | null;
};

type GroupPayload = {
    id: number;
    name: string;
    description: string | null;
    visibility: string;
    members_count: number;
    is_member: boolean;
    join_url: string;
    course: { title: string; url: string } | null;
    members: MemberPayload[];
};

const props = defineProps<{
    group: GroupPayload;
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

function joinGroup() {
    router.post(props.group.join_url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="group.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Link
            href="/student/community/groups"
            class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="h-4 w-4" />
            Groups
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <LockKeyhole class="h-5 w-5 text-amber-600" />
                    <h1 class="text-2xl font-semibold tracking-normal">
                        {{ group.name }}
                    </h1>
                </div>
                <p
                    class="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    {{ group.description }}
                </p>
                <Link
                    v-if="group.course"
                    :href="group.course.url"
                    class="mt-3 inline-flex text-sm font-semibold text-teal-700"
                >
                    {{ group.course.title }}
                </Link>
            </div>
            <button
                v-if="!group.is_member"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white"
                @click="joinGroup"
            >
                <UserPlus class="h-4 w-4" />
                Join
            </button>
        </div>

        <section class="rounded-lg border bg-card p-5">
            <div class="mb-4 flex items-center gap-2">
                <Users class="h-5 w-5 text-teal-600" />
                <h2 class="font-semibold">Members</h2>
                <span class="text-sm text-muted-foreground">
                    {{ group.members_count }}
                </span>
            </div>
            <div v-if="group.members.length" class="grid gap-3 md:grid-cols-2">
                <div
                    v-for="member in group.members"
                    :key="member.id"
                    class="rounded-md border p-3"
                >
                    <p class="font-medium">{{ member.name || 'Member' }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ member.role }}
                    </p>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No active members yet.
            </p>
        </section>
    </div>
</template>
