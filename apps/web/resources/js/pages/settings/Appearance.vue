<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';
import { trans } from '@/lib/trans';

// siehe auth/Register.vue: defineOptions({layout}) laeuft zu frueh fuer trans().
watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: trans('Appearance settings'),
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="trans('Appearance settings')" />

    <h1 class="sr-only">{{ trans('Appearance settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="trans('Appearance settings')"
            :description="
                trans('Update the appearance settings for your account')
            "
        />
        <AppearanceTabs />
    </div>
</template>
