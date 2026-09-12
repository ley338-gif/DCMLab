<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { trans } from '@/lib/trans';
import { dashboard } from '@/routes';

// siehe auth/Register.vue: defineOptions({layout}) laeuft zu frueh fuer trans().
watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: trans('Dashboard'),
                href: dashboard(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="trans('Dashboard')" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
            >
                <PlaceholderPattern />
            </div>
            <div
                class="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border"
            >
                <PlaceholderPattern />
            </div>
        </div>
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min"
        >
            <PlaceholderPattern />
        </div>
    </div>
</template>
