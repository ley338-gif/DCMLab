<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';
import { Progress } from '@/components/ui/progress';
import { trans } from '@/lib/trans';
import { show as showTrack } from '@/routes/tracks';

type Track = {
    slug: string;
    title_key: string;
    title: { de: string } | null;
    lessons_count: number;
    completed_lessons_count: number;
};

const props = defineProps<{
    track: Track;
}>();

const title = computed(
    () => props.track.title?.de ?? trans(props.track.title_key),
);

const percent = computed(() => {
    if (props.track.lessons_count === 0) {
        return 0;
    }

    return Math.round(
        (props.track.completed_lessons_count / props.track.lessons_count) * 100,
    );
});

const isCompleted = computed(
    () =>
        props.track.lessons_count > 0 &&
        props.track.completed_lessons_count >= props.track.lessons_count,
);

const ctaLabel = computed(() => {
    if (isCompleted.value) {
        return trans('Ansehen');
    }

    return props.track.completed_lessons_count > 0
        ? trans('Weiter')
        : trans('Start');
});
</script>

<template>
    <Link :href="showTrack(track.slug)" class="block h-full">
        <div
            class="hover:border-foreground/30 flex h-full flex-col justify-between gap-3 rounded-lg border p-4 transition-colors"
        >
            <div>
                <div class="flex items-start justify-between gap-2">
                    <span class="font-medium">{{ title }}</span>
                    <CheckCircle2
                        v-if="isCompleted"
                        class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                        aria-hidden="true"
                    />
                </div>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{
                        trans(':completed von :total Lektionen', {
                            completed: track.completed_lessons_count,
                            total: track.lessons_count,
                        })
                    }}
                </p>
                <Progress :model-value="percent" class="mt-3" />
            </div>

            <span
                class="text-primary inline-flex items-center gap-1 text-sm font-medium"
            >
                {{ ctaLabel }}
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </span>
        </div>
    </Link>
</template>
