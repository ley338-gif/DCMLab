<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { show as showLab } from '@/routes/labs';
import { show as showTrack } from '@/routes/tracks';

type Recommended = {
    type: 'lab' | 'lesson' | 'track';
    reason_code:
        | 'fits_current_track'
        | 'prerequisites_met'
        | 'track_completed'
        | 'beginner_recommendation';
    title: string | null;
    title_key: string | null;
    slug: string | null;
    lesson_id: string | null;
    completed_track_title: string | null;
    completed_track_title_key: string | null;
};

const props = defineProps<{
    recommended: Recommended | null;
}>();

const title = computed(() => {
    if (props.recommended === null) {
        return '';
    }

    return (
        props.recommended.title ??
        (props.recommended.title_key ? trans(props.recommended.title_key) : '')
    );
});

const reason = computed(() => {
    if (props.recommended === null) {
        return '';
    }

    switch (props.recommended.reason_code) {
        case 'fits_current_track':
            return trans('Passt zu deinem aktuellen Track.');
        case 'prerequisites_met':
            return trans('Du hast die Grundlagen dafür bereits abgeschlossen.');
        case 'track_completed': {
            const completedTrack =
                props.recommended.completed_track_title ??
                (props.recommended.completed_track_title_key
                    ? trans(props.recommended.completed_track_title_key)
                    : '');

            return trans('Du hast :track abgeschlossen.', {
                track: completedTrack,
            });
        }
        case 'beginner_recommendation':
            return trans('Ein guter Einstieg für Neulinge.');
        default:
            return '';
    }
});

const href = computed(() => {
    const recommended = props.recommended;

    if (recommended === null) {
        return null;
    }

    if (recommended.type === 'lab' && recommended.slug) {
        return showLab(recommended.slug);
    }

    if (recommended.type === 'track' && recommended.slug) {
        return showTrack(recommended.slug);
    }

    if (recommended.type === 'lesson' && recommended.lesson_id) {
        return showLesson(recommended.lesson_id);
    }

    return null;
});
</script>

<template>
    <section v-if="recommended && href" class="rounded-xl border p-5">
        <div class="flex items-center gap-2">
            <Sparkles class="text-chart-2 size-4" aria-hidden="true" />
            <h2 class="font-semibold">{{ trans('Empfohlen als Nächstes') }}</h2>
        </div>
        <Link
            :href="href"
            class="hover:bg-accent/50 mt-3 block rounded-lg border p-3"
        >
            <p class="font-medium">{{ title }}</p>
            <p
                class="text-muted-foreground mt-1 flex items-center gap-1 text-sm"
            >
                {{ reason }}
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </p>
        </Link>
    </section>
</template>
