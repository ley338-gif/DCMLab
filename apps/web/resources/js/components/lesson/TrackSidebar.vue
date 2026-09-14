<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Circle, CircleDot } from '@lucide/vue';
import { ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { show as showTrack } from '@/routes/tracks';

export type SidebarLesson = {
    lesson_id: string;
    title: string;
    order: number;
    status: 'completed' | 'current' | 'todo';
};

export type SidebarTrackSummary = {
    slug: string;
    title_key: string;
    lessons_count: number;
    completed_lessons_count: number;
};

defineProps<{
    currentTrack: { slug: string; title_key: string; lessons: SidebarLesson[] };
    otherTracks: SidebarTrackSummary[];
    overall: { completed: number; total: number };
}>();

const otherTracksOpen = ref(false);
</script>

<template>
    <nav class="track-sidebar" :aria-label="trans('Trackfortschritt')">
        <div class="track-sidebar-group">
            <p class="track-sidebar-heading">
                {{ trans(currentTrack.title_key) }}
            </p>
            <ul class="track-sidebar-lessons">
                <li
                    v-for="lesson in currentTrack.lessons"
                    :key="lesson.lesson_id"
                >
                    <Link
                        :href="showLesson(lesson.lesson_id)"
                        class="track-sidebar-lesson"
                        :class="`is-${lesson.status}`"
                        :aria-current="
                            lesson.status === 'current' ? 'page' : undefined
                        "
                    >
                        <CheckCircle2
                            v-if="lesson.status === 'completed'"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <CircleDot
                            v-else-if="lesson.status === 'current'"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <Circle
                            v-else
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>{{ lesson.title }}</span>
                    </Link>
                </li>
            </ul>
        </div>

        <Collapsible
            v-if="otherTracks.length"
            v-model:open="otherTracksOpen"
            class="track-sidebar-group"
        >
            <CollapsibleTrigger class="track-sidebar-collapse-trigger">
                {{ trans('Andere Tracks') }}
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': otherTracksOpen }"
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <ul class="track-sidebar-other-tracks">
                    <li v-for="track in otherTracks" :key="track.slug">
                        <Link :href="showTrack(track.slug)">
                            <span>{{ trans(track.title_key) }}</span>
                            <small
                                >{{ track.completed_lessons_count }}/{{
                                    track.lessons_count
                                }}</small
                            >
                        </Link>
                    </li>
                </ul>
            </CollapsibleContent>
        </Collapsible>

        <div v-if="overall.total > 0" class="track-sidebar-progress">
            <p class="track-sidebar-progress-label">
                {{ trans('Dein Fortschritt') }}
            </p>
            <div class="track-sidebar-progress-bar">
                <div
                    class="track-sidebar-progress-fill"
                    :style="{
                        width: `${Math.round((overall.completed / overall.total) * 100)}%`,
                    }"
                />
            </div>
            <p class="track-sidebar-progress-count">
                {{
                    trans(':completed von :total Lektionen', {
                        completed: overall.completed,
                        total: overall.total,
                    })
                }}
            </p>
        </div>
    </nav>
</template>
