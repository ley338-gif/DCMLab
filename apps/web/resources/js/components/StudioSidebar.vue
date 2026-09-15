<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Container, LayoutGrid, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
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
import { trans } from '@/lib/trans';
import { index as authorPanelIndex } from '@/routes/author';
import { index as studioIndex } from '@/routes/studio';
import { index as sandboxTemplatesIndex } from '@/routes/studio/sandbox-templates';
import type { NavItem } from '@/types';

// Studio ergaenzt das bestehende Autoren-Panel ressourcenweise (ADR 0094
// Abschnitt 11), statt es in einem Zug abzuloesen -- der Ruecklink haelt
// beide Bereiche gegenseitig auffindbar, solange das so bleibt.
const mainNavItems = computed<NavItem[]>(() => [
    {
        title: trans('Übersicht'),
        href: studioIndex(),
        icon: LayoutGrid,
    },
    {
        title: trans('Sandbox-Vorlagen'),
        href: sandboxTemplatesIndex(),
        icon: Container,
    },
    {
        title: trans('Autoren-Panel'),
        href: authorPanelIndex(),
        icon: ShieldCheck,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="studioIndex()">
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
