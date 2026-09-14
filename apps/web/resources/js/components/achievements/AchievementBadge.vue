<script setup lang="ts">
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import { trans } from '@/lib/trans';
import type { Achievement } from '@/types/achievement';

const props = withDefaults(
    defineProps<{
        achievement: Achievement;
        size?: 'sm' | 'md' | 'lg';
        showName?: boolean;
        showDate?: boolean;
        locked?: boolean;
    }>(),
    {
        size: 'md',
        showName: true,
        showDate: false,
        locked: undefined,
    },
);

// Groessen decken die im Auftrag genannten Bereiche ab: Dashboard 80-110px,
// Achievement-Uebersicht 120-160px, Public-Profile-Featured-Badge 160-220px.
const sizePx: Record<'sm' | 'md' | 'lg', number> = {
    sm: 80,
    md: 110,
    lg: 180,
};

const isLocked = computed(() => props.locked ?? !props.achievement.unlocked);

const dimension = computed(() => `${sizePx[props.size]}px`);

const altText = computed(() =>
    isLocked.value
        ? trans('Achievement :name – noch nicht freigeschaltet', { name: props.achievement.name })
        : trans('Achievement: :name', { name: props.achievement.name }),
);

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="flex flex-col items-center gap-1.5 text-center">
        <div
            class="relative overflow-hidden rounded-lg border bg-card"
            :style="{ width: dimension, height: dimension }"
        >
            <img
                :src="achievement.image"
                :alt="altText"
                loading="lazy"
                :width="sizePx[size]"
                :height="sizePx[size]"
                class="h-full w-full"
                :class="isLocked ? 'achievement-badge-locked' : ''"
                style="aspect-ratio: 1 / 1; object-fit: contain"
            />
            <span
                v-if="isLocked"
                class="bg-background/80 text-muted-foreground absolute right-1 bottom-1 rounded-full p-1"
                aria-hidden="true"
            >
                <Lock class="size-3" />
            </span>
        </div>
        <div v-if="showName" class="flex flex-col gap-0.5">
            <span
                class="text-xs leading-tight font-medium"
                :class="isLocked ? 'text-muted-foreground' : ''"
            >
                {{ achievement.name }}
            </span>
            <span
                v-if="showDate && achievement.unlocked_at"
                class="text-muted-foreground text-[11px]"
            >
                {{ formatDate(achievement.unlocked_at) }}
            </span>
        </div>
    </div>
</template>

<style scoped>
.achievement-badge-locked {
    filter: grayscale(1) brightness(0.45) contrast(0.8);
    opacity: 0.45;
}
</style>
