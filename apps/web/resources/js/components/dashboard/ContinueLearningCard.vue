<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { Progress } from '@/components/ui/progress';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { index as tracksIndex, show as showTrack } from '@/routes/tracks';

type ContinueLearning = {
    track_slug: string;
    track_title_key: string;
    track_title: { de: string } | null;
    lesson_id: string;
    lesson_title: string;
    position: number;
    lessons_count: number;
    completed_lessons_count: number;
};

type BeginnerRecommendation = {
    type: 'lab' | 'lesson' | 'track';
    title: string | null;
    title_key: string | null;
    slug: string | null;
} | null;

const props = defineProps<{
    continueLearning: ContinueLearning | null;
    // Nur fuer den Empty-State relevant: eine konkrete Track-Empfehlung,
    // falls DashboardHomeService::recommendedNext() eine gefunden hat --
    // sonst der generische Link auf den vollen Katalog.
    beginnerRecommendation: BeginnerRecommendation;
}>();

const trackTitle = computed(() => {
    if (props.continueLearning === null) {
        return '';
    }

    return (
        props.continueLearning.track_title?.de ??
        trans(props.continueLearning.track_title_key)
    );
});

const progressPercent = computed(() => {
    if (
        props.continueLearning === null ||
        props.continueLearning.lessons_count === 0
    ) {
        return 0;
    }

    return Math.round(
        (props.continueLearning.completed_lessons_count /
            props.continueLearning.lessons_count) *
            100,
    );
});

const beginnerTrackTitle = computed(() => {
    const rec = props.beginnerRecommendation;

    if (rec === null || rec.type !== 'track') {
        return null;
    }

    return rec.title ?? (rec.title_key ? trans(rec.title_key) : null);
});
</script>

<template>
    <section
        class="from-primary/5 rounded-xl border bg-gradient-to-br to-transparent p-6"
        aria-labelledby="continue-learning-heading"
    >
        <template v-if="continueLearning">
            <p
                id="continue-learning-heading"
                class="text-muted-foreground text-sm font-medium"
            >
                {{ trans('Weiterlernen') }}
            </p>
            <p class="mt-1 text-lg font-semibold">{{ trackTitle }}</p>
            <p class="text-muted-foreground">
                {{ continueLearning.lesson_title }}
            </p>

            <p class="text-muted-foreground mt-4 text-sm">
                {{
                    trans('Lektion :position von :total', {
                        position: continueLearning.position,
                        total: continueLearning.lessons_count,
                    })
                }}
            </p>
            <Progress :model-value="progressPercent" class="mt-2" />

            <Link
                :href="showLesson(continueLearning.lesson_id)"
                class="bg-primary text-primary-foreground mt-5 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold hover:opacity-90"
            >
                {{ trans('Weiterlernen') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </template>

        <template v-else>
            <h2 id="continue-learning-heading" class="text-lg font-semibold">
                {{ trans('Starte deinen ersten Track') }}
            </h2>
            <p class="text-muted-foreground mt-1">
                {{ trans('Deine Lernreise beginnt hier.') }}
            </p>

            <Link
                :href="
                    beginnerRecommendation?.type === 'track' &&
                    beginnerRecommendation.slug
                        ? showTrack(beginnerRecommendation.slug)
                        : tracksIndex()
                "
                class="bg-primary text-primary-foreground mt-5 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold hover:opacity-90"
            >
                {{
                    beginnerTrackTitle
                        ? trans(':track starten', { track: beginnerTrackTitle })
                        : trans('Track auswählen')
                }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </template>
    </section>
</template>
