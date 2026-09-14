<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { trans } from '@/lib/trans';

export type NavNeighbor = { href: string; label: string; title: string } | null;

defineProps<{ prev: NavNeighbor; next: NavNeighbor }>();
</script>

<template>
    <nav
        v-if="prev || next"
        class="lesson-nav"
        :aria-label="trans('Navigation')"
    >
        <Link
            v-if="prev"
            :href="prev.href"
            class="lesson-nav-link lesson-nav-prev"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            <span>
                <small>{{ prev.label }}</small>
                {{ prev.title }}
            </span>
        </Link>
        <span v-else />

        <Link
            v-if="next"
            :href="next.href"
            class="lesson-nav-link lesson-nav-next"
        >
            <span>
                <small>{{ next.label }}</small>
                {{ next.title }}
            </span>
            <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
    </nav>
</template>
