<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const props = withDefaults(
    defineProps<{
        accountType?: 'student' | 'creator';
        creatorAgreementVersion?: string;
        passwordRules: string;
    }>(),
    {
        accountType: 'student',
        creatorAgreementVersion: '2026-07-19',
    },
);

const isCreator = computed(() => props.accountType === 'creator');
const title = computed(() =>
    isCreator.value ? 'Create a creator account' : 'Create a student account',
);
const description = computed(() =>
    isCreator.value
        ? 'Apply to publish blogs and courses under admin approval'
        : 'Start learning with courses, notes, progress, and certificates',
);

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head :title="title" />

    <div class="mb-6 grid gap-3 text-center">
        <div class="grid gap-1">
            <h1 class="text-xl font-semibold tracking-normal">{{ title }}</h1>
            <p class="text-sm text-muted-foreground">{{ description }}</p>
        </div>

        <div class="grid grid-cols-2 gap-2 rounded-lg bg-muted p-1 text-sm">
            <TextLink
                href="/register/student"
                class="rounded-md px-3 py-2 text-center font-medium no-underline"
                :class="
                    !isCreator
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground'
                "
            >
                Student
            </TextLink>
            <TextLink
                href="/register/creator"
                class="rounded-md px-3 py-2 text-center font-medium no-underline"
                :class="
                    isCreator
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground'
                "
            >
                Creator
            </TextLink>
        </div>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <input type="hidden" name="account_type" :value="accountType" />

        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <template v-if="isCreator">
                <div class="grid gap-2">
                    <Label for="expertise">Creator expertise</Label>
                    <Input
                        id="expertise"
                        type="text"
                        required
                        :tabindex="3"
                        autocomplete="organization-title"
                        name="expertise"
                        placeholder="Laravel, design systems, DevOps..."
                    />
                    <InputError :message="errors.expertise" />
                </div>

                <div class="grid gap-2">
                    <Label for="linkedin_url">LinkedIn profile URL</Label>
                    <Input
                        id="linkedin_url"
                        type="url"
                        required
                        :tabindex="4"
                        autocomplete="url"
                        name="linkedin_url"
                        placeholder="https://www.linkedin.com/in/your-profile"
                    />
                    <InputError :message="errors.linkedin_url" />
                    <p class="text-xs text-muted-foreground">
                        Your public creator profile may link to this after
                        approval.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="website_url">Website URL</Label>
                    <Input
                        id="website_url"
                        type="url"
                        :tabindex="5"
                        autocomplete="url"
                        name="website_url"
                        placeholder="https://example.com"
                    />
                    <InputError :message="errors.website_url" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input
                        id="phone"
                        type="tel"
                        :tabindex="6"
                        autocomplete="tel"
                        name="phone"
                        placeholder="+1 555 0100"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="application_reason"
                        >Why do you want to publish?</Label
                    >
                    <textarea
                        id="application_reason"
                        required
                        :tabindex="7"
                        name="application_reason"
                        class="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="Tell us what you plan to teach or write about."
                    />
                    <InputError :message="errors.application_reason" />
                </div>

                <div class="grid gap-2 rounded-md border bg-muted/40 p-4">
                    <label
                        for="creator_agreement_accepted"
                        class="flex items-start gap-3 text-sm leading-6"
                    >
                        <input
                            id="creator_agreement_accepted"
                            type="checkbox"
                            required
                            :tabindex="8"
                            name="creator_agreement_accepted"
                            value="1"
                            class="mt-1 h-4 w-4 rounded border-input text-teal-600 focus:ring-teal-600"
                        />
                        <span>
                            I accept the Scratch Learning creator agreement
                            version {{ creatorAgreementVersion }}. I understand
                            Scratch Learning does not pay creators, published
                            content is promotional/educational, my public
                            profile or LinkedIn link may be shown, and I own or
                            have rights to everything I upload.
                        </span>
                    </label>
                    <InputError :message="errors.creator_agreement_accepted" />
                    <p class="text-xs text-muted-foreground">
                        Read the
                        <TextLink
                            href="/terms-and-conditions"
                            class="underline underline-offset-4"
                        >
                            Terms & Conditions
                        </TextLink>
                        and
                        <TextLink
                            href="/privacy-policy"
                            class="underline underline-offset-4"
                        >
                            Privacy Policy
                        </TextLink>
                        before applying.
                    </p>
                </div>
            </template>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    required
                    :tabindex="isCreator ? 9 : 3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="isCreator ? 10 : 4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Confirm password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="isCreator ? 11 : 5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                {{ isCreator ? 'Apply as creator' : 'Create student account' }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="isCreator ? 12 : 6"
                >Log in</TextLink
            >
        </div>
    </Form>
</template>
