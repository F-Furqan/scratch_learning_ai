<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 96 96"
        :class="className"
        role="img"
        aria-label="Scratch Learning"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient
                id="sl-app-mark-bg"
                x1="12"
                y1="10"
                x2="84"
                y2="88"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#FBBF24" />
                <stop offset="0.45" stop-color="#F97316" />
                <stop offset="1" stop-color="#14B8A6" />
            </linearGradient>
            <linearGradient
                id="sl-app-mark-page"
                x1="26"
                y1="22"
                x2="70"
                y2="72"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#FFFFFF" />
                <stop offset="1" stop-color="#DFF7F3" />
            </linearGradient>
        </defs>
        <rect width="96" height="96" rx="22" fill="#08111F" />
        <rect
            x="7"
            y="7"
            width="82"
            height="82"
            rx="18"
            fill="url(#sl-app-mark-bg)"
        />
        <path
            d="M25 27c8 1 15 3.5 23 8v34c-7.4-4.4-14.6-6.8-23-7.8V27Z"
            fill="url(#sl-app-mark-page)"
            opacity="0.96"
        />
        <path
            d="M48 35c8-4.5 15-7 23-8v34.2c-8.4 1-15.6 3.4-23 7.8V35Z"
            fill="url(#sl-app-mark-page)"
            opacity="0.86"
        />
        <path
            d="M48 35v34"
            fill="none"
            stroke="#0F172A"
            stroke-width="2.6"
            stroke-linecap="round"
            opacity="0.28"
        />
        <path
            d="M29 43c5.4-6.6 16.8-6 21.2 1.6 4.8 8.4-2.1 17.2-11 14.3-5-1.6-7.4-6.2-5.8-10.6 2-5.5 9.1-7.1 14-3.1"
            fill="none"
            stroke="#08111F"
            stroke-width="6.2"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="M58 35l2.2 5.6 5.8 2-5.8 2.1-2.2 5.5-2.2-5.5-5.8-2.1 5.8-2 2.2-5.6Z"
            fill="#FDE68A"
        />
        <path
            d="M68 58l1.4 3.4 3.6 1.4-3.6 1.3-1.4 3.6-1.4-3.6-3.6-1.3 3.6-1.4 1.4-3.4Z"
            fill="#CCFBF1"
        />
    </svg>
</template>
