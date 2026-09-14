<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    FlaskConical,
    GraduationCap,
    LayoutGrid,
    ListChecks,
    Menu,
    Moon,
    PanelRight,
    Sun,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { trans } from '@/lib/trans';
import { dashboard, home } from '@/routes';
import { index as glossaryIndex } from '@/routes/glossary';
import { index as nodesIndex } from '@/routes/nodes';
import { index as reviewIndex } from '@/routes/review';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { appearance, updateAppearance } = useAppearance();

function toggleAppearance() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}
</script>

<template>
    <div class="lesson-app">
        <header class="lesson-app-header">
            <div class="lesson-app-header-inner">
                <div class="lesson-app-header-left">
                    <Sheet>
                        <SheetTrigger
                            class="lesson-app-drawer-trigger"
                            :aria-label="trans('Navigation öffnen')"
                        >
                            <Menu class="size-5" aria-hidden="true" />
                        </SheetTrigger>
                        <SheetContent side="left" class="lesson-app-drawer">
                            <SheetHeader>
                                <SheetTitle>{{
                                    trans('Navigation')
                                }}</SheetTitle>
                            </SheetHeader>
                            <div class="lesson-app-drawer-body">
                                <nav
                                    class="lesson-app-drawer-nav"
                                    :aria-label="trans('Hauptnavigation')"
                                >
                                    <Link :href="home()">
                                        <GraduationCap
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span>{{ trans('Tracks') }}</span>
                                    </Link>
                                    <Link :href="dashboard()">
                                        <LayoutGrid
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span>{{ trans('Dashboard') }}</span>
                                    </Link>
                                    <Link :href="nodesIndex()">
                                        <FlaskConical
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span>{{ trans('Labs') }}</span>
                                    </Link>
                                    <Link :href="reviewIndex()">
                                        <ListChecks
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span>{{
                                            trans('Wissen testen')
                                        }}</span>
                                    </Link>
                                    <Link :href="glossaryIndex()">
                                        <BookOpenText
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        <span>{{ trans('Glossar') }}</span>
                                    </Link>
                                </nav>
                                <slot name="sidebar" />
                            </div>
                        </SheetContent>
                    </Sheet>

                    <Link :href="home()" class="lesson-app-logo">
                        <AppLogo />
                    </Link>
                </div>

                <nav
                    class="lesson-app-nav"
                    :aria-label="trans('Hauptnavigation')"
                >
                    <Link :href="home()">
                        <GraduationCap class="size-4" aria-hidden="true" />
                        {{ trans('Tracks') }}
                    </Link>
                    <Link :href="dashboard()">
                        <LayoutGrid class="size-4" aria-hidden="true" />
                        {{ trans('Dashboard') }}
                    </Link>
                    <Link :href="nodesIndex()">
                        <FlaskConical class="size-4" aria-hidden="true" />
                        {{ trans('Labs') }}
                    </Link>
                    <Link :href="reviewIndex()">
                        <ListChecks class="size-4" aria-hidden="true" />
                        {{ trans('Wissen testen') }}
                    </Link>
                    <Link :href="glossaryIndex()">
                        <BookOpenText class="size-4" aria-hidden="true" />
                        {{ trans('Glossar') }}
                    </Link>
                </nav>

                <div class="lesson-app-header-right">
                    <button
                        type="button"
                        class="lesson-app-icon-btn"
                        :aria-label="trans('Darstellung umschalten')"
                        @click="toggleAppearance"
                    >
                        <Moon
                            v-if="appearance !== 'dark'"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Sun v-else class="size-4" aria-hidden="true" />
                    </button>

                    <Sheet>
                        <SheetTrigger
                            class="lesson-app-icon-btn lesson-app-toc-trigger"
                            :aria-label="trans('In dieser Lektion')"
                        >
                            <PanelRight class="size-4" aria-hidden="true" />
                        </SheetTrigger>
                        <SheetContent side="right" class="lesson-app-drawer">
                            <SheetHeader>
                                <SheetTitle>{{
                                    trans('In dieser Lektion')
                                }}</SheetTitle>
                            </SheetHeader>
                            <div class="lesson-app-drawer-body">
                                <slot name="toc" />
                            </div>
                        </SheetContent>
                    </Sheet>

                    <DropdownMenu>
                        <DropdownMenuTrigger class="lesson-app-user-trigger">
                            <UserInfo :user="user" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>

        <div class="lesson-app-body">
            <aside class="lesson-app-col lesson-app-col-sidebar">
                <slot name="sidebar" />
            </aside>

            <main class="lesson-app-col lesson-app-col-main">
                <slot />
            </main>

            <aside class="lesson-app-col lesson-app-col-toc">
                <slot name="toc" />
            </aside>
        </div>
    </div>
</template>
