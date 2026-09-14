<script setup lang="ts">
import { trans } from '@/lib/trans';
import type { Achievement } from '@/types/achievement';

defineProps<{
    achievement: Achievement;
}>();
</script>

<template>
    <div
        class="achievement-unlock-toast bg-card text-card-foreground flex items-center gap-3 rounded-lg border p-3 shadow-sm"
    >
        <img
            :src="achievement.image"
            :alt="trans('Achievement: :name', { name: achievement.name })"
            loading="lazy"
            width="48"
            height="48"
            class="shrink-0 rounded-md border"
            style="aspect-ratio: 1 / 1; object-fit: contain"
        />
        <div class="flex flex-col gap-0.5">
            <span
                class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase"
            >
                {{ trans('Achievement freigeschaltet') }}
            </span>
            <span class="text-sm font-medium">{{ achievement.name }}</span>
            <span class="text-muted-foreground text-xs">{{
                achievement.description
            }}</span>
        </div>
    </div>
</template>

<style scoped>
.achievement-unlock-toast {
    animation: achievement-unlock-in 300ms ease-out;
}

@keyframes achievement-unlock-in {
    from {
        opacity: 0;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .achievement-unlock-toast {
        animation: none;
    }
}
</style>
