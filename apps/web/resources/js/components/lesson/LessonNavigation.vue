<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';

type NeighborLesson = { lesson_id: string; title: string } | null;

defineProps<{ prev: NeighborLesson; next: NeighborLesson }>();
</script>

<template>
    <nav
        v-if="prev || next"
        class="lesson-nav"
        :aria-label="trans('Lektionsnavigation')"
    >
        <Link
            v-if="prev"
            :href="showLesson(prev.lesson_id)"
            class="lesson-nav-link lesson-nav-prev"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            <span>
                <small>{{ trans('Vorherige Lektion') }}</small>
                {{ prev.title }}
            </span>
        </Link>
        <span v-else />

        <Link
            v-if="next"
            :href="showLesson(next.lesson_id)"
            class="lesson-nav-link lesson-nav-next"
        >
            <span>
                <small>{{ trans('Nächste Lektion') }}</small>
                {{ next.title }}
            </span>
            <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
    </nav>
</template>
