<script setup lang="ts">
import { computed } from 'vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import type { Achievement } from '@/types/achievement';

const props = defineProps<{
    achievement: Achievement;
}>();

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

const unlockedLabel = computed(() =>
    props.achievement.unlocked_at
        ? trans('Unlocked :date', { date: formatDate(props.achievement.unlocked_at) })
        : trans('Unlocked'),
);
</script>

<template>
    <Card class="flex flex-col items-center gap-3 p-4 text-center">
        <CardContent class="flex flex-col items-center gap-3 p-0">
            <AchievementBadge :achievement="achievement" size="lg" :show-name="false" />
            <div class="flex flex-col gap-1">
                <span class="font-medium">{{ achievement.name }}</span>
                <span class="text-muted-foreground text-sm">{{
                    achievement.description
                }}</span>
            </div>
            <span
                v-if="achievement.unlocked"
                class="text-xs font-medium text-emerald-600 dark:text-emerald-400"
            >
                ✓ {{ unlockedLabel }}
            </span>
        </CardContent>
    </Card>
</template>
