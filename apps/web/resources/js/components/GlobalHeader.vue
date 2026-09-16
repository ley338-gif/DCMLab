<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    FlaskConical,
    GraduationCap,
    LayoutDashboard,
    LayoutGrid,
    ListChecks,
    Menu,
    Moon,
    ShieldCheck,
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
import { dashboard, home, login, register } from '@/routes';
import { index as authorPanelIndex } from '@/routes/author';
import { index as glossaryIndex } from '@/routes/glossary';
import { index as nodesIndex } from '@/routes/nodes';
import { index as reviewIndex } from '@/routes/review';
import { index as studioIndex } from '@/routes/studio';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { appearance, updateAppearance } = useAppearance();

function toggleAppearance() {
    updateAppearance(appearance.value === 'dark' ? 'light' : 'dark');
}

function isActive(prefix: string): boolean {
    const url = page.url;

    return prefix === '/de' ? url === '/de' : url.startsWith(prefix);
}
</script>

<template>
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
                            <SheetTitle>{{ trans('Navigation') }}</SheetTitle>
                        </SheetHeader>
                        <div class="lesson-app-drawer-body">
                            <nav
                                class="lesson-app-drawer-nav"
                                :aria-label="trans('Hauptnavigation')"
                            >
                                <Link
                                    :href="home()"
                                    :class="{
                                        'is-active':
                                            isActive('/de/tracks') ||
                                            isActive('/de'),
                                    }"
                                >
                                    <GraduationCap
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Tracks') }}</span>
                                </Link>
                                <Link
                                    v-if="user"
                                    :href="dashboard()"
                                    :class="{
                                        'is-active': isActive('/de/dashboard'),
                                    }"
                                >
                                    <LayoutGrid
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Dashboard') }}</span>
                                </Link>
                                <Link
                                    :href="nodesIndex()"
                                    :class="{
                                        'is-active': isActive('/de/nodes'),
                                    }"
                                >
                                    <FlaskConical
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{
                                        trans('Herausforderungen')
                                    }}</span>
                                </Link>
                                <Link
                                    v-if="user"
                                    :href="reviewIndex()"
                                    :class="{
                                        'is-active': isActive('/de/review'),
                                    }"
                                >
                                    <ListChecks
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Wissen testen') }}</span>
                                </Link>
                                <Link
                                    v-if="user && user.role !== 'learner'"
                                    :href="authorPanelIndex()"
                                    :class="{
                                        'is-active': isActive('/de/author'),
                                    }"
                                >
                                    <ShieldCheck
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Autoren-Panel') }}</span>
                                </Link>
                                <Link
                                    v-if="user && user.role !== 'learner'"
                                    :href="studioIndex()"
                                    :class="{
                                        'is-active': isActive('/de/studio'),
                                    }"
                                >
                                    <LayoutDashboard
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Studio') }}</span>
                                </Link>
                                <Link
                                    :href="glossaryIndex()"
                                    :class="{
                                        'is-active': isActive('/de/glossar'),
                                    }"
                                >
                                    <BookOpenText
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <span>{{ trans('Glossar') }}</span>
                                </Link>
                            </nav>
                            <slot name="mobileExtra" />
                        </div>
                    </SheetContent>
                </Sheet>

                <Link :href="home()" class="lesson-app-logo">
                    <AppLogo />
                </Link>
            </div>

            <nav class="lesson-app-nav" :aria-label="trans('Hauptnavigation')">
                <Link
                    :href="home()"
                    :class="{
                        'is-active': isActive('/de/tracks') || isActive('/de'),
                    }"
                >
                    <GraduationCap class="size-4" aria-hidden="true" />
                    {{ trans('Tracks') }}
                </Link>
                <Link
                    v-if="user"
                    :href="dashboard()"
                    :class="{ 'is-active': isActive('/de/dashboard') }"
                >
                    <LayoutGrid class="size-4" aria-hidden="true" />
                    {{ trans('Dashboard') }}
                </Link>
                <Link
                    :href="nodesIndex()"
                    :class="{ 'is-active': isActive('/de/nodes') }"
                >
                    <FlaskConical class="size-4" aria-hidden="true" />
                    {{ trans('Herausforderungen') }}
                </Link>
                <Link
                    v-if="user"
                    :href="reviewIndex()"
                    :class="{ 'is-active': isActive('/de/review') }"
                >
                    <ListChecks class="size-4" aria-hidden="true" />
                    {{ trans('Wissen testen') }}
                </Link>
                <Link
                    v-if="user && user.role !== 'learner'"
                    :href="authorPanelIndex()"
                    :class="{ 'is-active': isActive('/de/author') }"
                >
                    <ShieldCheck class="size-4" aria-hidden="true" />
                    {{ trans('Autoren-Panel') }}
                </Link>
                <Link
                    v-if="user && user.role !== 'learner'"
                    :href="studioIndex()"
                    :class="{ 'is-active': isActive('/de/studio') }"
                >
                    <LayoutDashboard class="size-4" aria-hidden="true" />
                    {{ trans('Studio') }}
                </Link>
                <Link
                    :href="glossaryIndex()"
                    :class="{ 'is-active': isActive('/de/glossar') }"
                >
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

                <slot name="actions" />

                <DropdownMenu v-if="user">
                    <DropdownMenuTrigger class="lesson-app-user-trigger">
                        <UserInfo :user="user" />
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>

                <template v-else>
                    <Link :href="login()" class="lesson-app-guest-link">{{
                        trans('Log in')
                    }}</Link>
                    <Link
                        :href="register()"
                        class="lesson-app-guest-link lesson-app-guest-link-primary"
                        >{{ trans('Register') }}</Link
                    >
                </template>
            </div>
        </div>
    </header>
</template>
