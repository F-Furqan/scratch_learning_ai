<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps<{
    profile: {
        name: string;
        email: string;
        phone: string | null;
        bio: string | null;
        expertise: string | null;
        linkedin_url: string | null;
        website_url: string | null;
        application_reason: string | null;
        status: string | null;
        admin_notes: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Creator profile',
                href: '/creator/profile',
            },
        ],
    },
});
</script>

<template>
    <Head title="Creator Profile" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-normal">
                Creator Profile
            </h1>
            <p class="text-sm text-muted-foreground">
                Manage the public creator details shown on approved blogs and
                courses.
            </p>
        </div>

        <section class="rounded-lg border bg-card p-6">
            <div class="mb-5 grid gap-2 rounded-md bg-muted p-4 text-sm">
                <p>
                    Status:
                    <strong class="capitalize">{{
                        profile.status || 'pending'
                    }}</strong>
                </p>
                <p v-if="profile.admin_notes" class="text-muted-foreground">
                    {{ profile.admin_notes }}
                </p>
            </div>

            <Form
                action="/creator/profile"
                method="patch"
                v-slot="{ errors, processing }"
                class="grid gap-5"
            >
                <div class="grid gap-2">
                    <Label for="name">Display name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        :default-value="profile.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="expertise">Expertise</Label>
                    <Input
                        id="expertise"
                        name="expertise"
                        :default-value="profile.expertise || ''"
                        placeholder="Laravel architecture, DevOps, product..."
                    />
                    <InputError :message="errors.expertise" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="linkedin_url">LinkedIn URL</Label>
                        <Input
                            id="linkedin_url"
                            name="linkedin_url"
                            type="url"
                            :default-value="profile.linkedin_url || ''"
                            placeholder="https://www.linkedin.com/in/..."
                        />
                        <InputError :message="errors.linkedin_url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="website_url">Website URL</Label>
                        <Input
                            id="website_url"
                            name="website_url"
                            type="url"
                            :default-value="profile.website_url || ''"
                            placeholder="https://example.com"
                        />
                        <InputError :message="errors.website_url" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        type="tel"
                        :default-value="profile.phone || ''"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="bio">Bio</Label>
                    <textarea
                        id="bio"
                        name="bio"
                        class="min-h-32 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="profile.bio || ''"
                    />
                    <InputError :message="errors.bio" />
                </div>

                <Button type="submit" class="w-fit" :disabled="processing">
                    Save profile
                </Button>
            </Form>
        </section>
    </div>
</template>
