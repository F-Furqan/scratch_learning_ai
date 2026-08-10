<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    ChevronRight,
    GraduationCap,
    Library,
    MessagesSquare,
    Newspaper,
    Settings,
    TrendingUp,
} from '@lucide/vue';
import type { Component } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { AdminNavigationGroup } from '@/types';

defineProps<{
    groups: AdminNavigationGroup[];
}>();

const iconMap: Record<string, Component> = {
    BadgeDollarSign,
    GraduationCap,
    Library,
    MessagesSquare,
    Newspaper,
    Settings,
    TrendingUp,
};

const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

const groupIsActive = (group: AdminNavigationGroup) =>
    group.items.some((item) => isCurrentOrParentUrl(item.href));
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Administration</SidebarGroupLabel>
        <SidebarMenu>
            <Collapsible
                v-for="group in groups"
                :key="group.key"
                as-child
                :default-open="groupIsActive(group)"
                class="group/admin-domain"
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :is-active="groupIsActive(group)"
                            :tooltip="group.label"
                        >
                            <component :is="iconMap[group.icon] || Settings" />
                            <span>{{ group.label }}</span>
                            <ChevronRight
                                class="ml-auto transition-transform duration-200 group-data-[state=open]/admin-domain:rotate-90"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="item in group.items"
                                :key="item.href"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(item.href)"
                                >
                                    <Link :href="item.href">
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
