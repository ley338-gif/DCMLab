<script setup lang="ts">
import GlobalHeader from '@/components/GlobalHeader.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="lesson-app">
        <GlobalHeader />
        <slot />
        <Toaster />
    </div>
</template>
