<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    FlaskConical,
    GraduationCap,
    LayoutDashboard,
    LayoutGrid,
    ShieldCheck,
} from '@lucide/vue';
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
import { dashboard, home } from '@/routes';
import { index as authorPanelIndex } from '@/routes/author';
import { index as glossaryIndex } from '@/routes/glossary';
import { index as nodesIndex } from '@/routes/nodes';
import { index as studioIndex } from '@/routes/studio';
import { index as tracksIndex } from '@/routes/tracks';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: trans('Tracks'),
            href: tracksIndex(),
            icon: GraduationCap,
        },
        {
            title: trans('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: trans('Herausforderungen'),
            href: nodesIndex(),
            icon: FlaskConical,
        },
        {
            title: trans('Glossar'),
            href: glossaryIndex(),
            icon: BookOpenText,
        },
    ];

    if (user.value && user.value.role !== 'learner') {
        items.push({
            title: trans('Autoren-Panel'),
            href: authorPanelIndex(),
            icon: ShieldCheck,
        });
        items.push({
            title: trans('Studio'),
            href: studioIndex(),
            icon: LayoutDashboard,
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
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
