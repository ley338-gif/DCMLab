<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import LessonHero from '@/components/lesson/LessonHero.vue';
import LessonNavigation from '@/components/lesson/LessonNavigation.vue';
import LessonSummary from '@/components/lesson/LessonSummary.vue';
import LessonToc from '@/components/lesson/LessonToc.vue';
import LearningObjectives from '@/components/lesson/LearningObjectives.vue';
import PracticeTask from '@/components/lesson/PracticeTask.vue';
import ToolGrid from '@/components/lesson/ToolGrid.vue';
import TrackSidebar, {
    type SidebarLesson,
    type SidebarTrackSummary,
} from '@/components/lesson/TrackSidebar.vue';
import QuizSection, {
    type QuizQuestion,
} from '@/components/quiz/QuizSection.vue';
import { Button } from '@/components/ui/button';
import { useLessonProseEnhancements } from '@/composables/useLessonProseEnhancements';
import { useLessonToc } from '@/composables/useLessonToc';
import LessonLayout from '@/layouts/lesson/LessonLayout.vue';
import { trans } from '@/lib/trans';
import { complete, reopen } from '@/routes/lessons';

type ToolbarTool = {
    slug: string;
    name: string;
    purpose: string | null;
    example: string | null;
    is_new: boolean;
};

type ToolbarData = {
    tools: ToolbarTool[];
    needs_sandbox: boolean;
    dataset: { note: string | null; file_count: number | null } | null;
    requires: { lesson_id: string; title: string }[];
    lab_node: {
        slug: string;
        title: string;
        difficulty: string;
        points: number;
    } | null;
    lab_optional: boolean;
};

type NeighborLesson = { lesson_id: string; title: string } | null;

const props = defineProps<{
    lesson: {
        lesson_id: string;
        title: string;
        teaser: string;
        objectives: string[];
        duration_minutes: number;
        level: string;
        body_html: string;
        body_after_quiz_html: string | null;
        position_in_track: number | null;
        track_lessons_count: number;
        prev: NeighborLesson;
        next: NeighborLesson;
    };
    track: { slug: string; title_key: string };
    quiz: QuizQuestion[];
    toolbar: ToolbarData;
    progress: { status: string; is_returning_visit: boolean };
    sidebar: {
        current_track: {
            slug: string;
            title_key: string;
            lessons: SidebarLesson[];
        };
        other_tracks: SidebarTrackSummary[];
        overall: { completed: number; total: number };
    };
}>();

const contentRef = ref<HTMLElement | null>(null);
const bodyHtml = computed(() => props.lesson.body_html);

const { entries, activeId, scrollToEntry } = useLessonToc(contentRef, bodyHtml);
useLessonProseEnhancements(contentRef);
</script>

<template>
    <Head :title="lesson.title" />

    <LessonLayout>
        <template #sidebar>
            <TrackSidebar
                :current-track="sidebar.current_track"
                :other-tracks="sidebar.other_tracks"
                :overall="sidebar.overall"
            />
        </template>

        <template #toc>
            <LessonToc
                :entries="entries"
                :active-id="activeId"
                @select="scrollToEntry"
            />
        </template>

        <LessonHero
            :track="track"
            :title="lesson.title"
            :teaser="lesson.teaser"
            :duration-minutes="lesson.duration_minutes"
            :level="lesson.level"
            :objectives-count="lesson.objectives.length"
            :position-in-track="lesson.position_in_track"
            :track-lessons-count="lesson.track_lessons_count"
            :requires="toolbar.requires"
        />

        <div ref="contentRef" class="lesson-content">
            <LearningObjectives :objectives="lesson.objectives" />

            <ToolGrid :tools="toolbar.tools" />

            <div class="lesson-prose" v-html="lesson.body_html" />

            <PracticeTask
                :lesson-id="lesson.lesson_id"
                :needs-sandbox="toolbar.needs_sandbox"
                :dataset="toolbar.dataset"
                :lab-node="toolbar.lab_node"
            />

            <QuizSection :lesson-id="lesson.lesson_id" :questions="quiz" />

            <div
                v-if="lesson.body_after_quiz_html"
                class="lesson-prose"
                v-html="lesson.body_after_quiz_html"
            />

            <LessonSummary :objectives="lesson.objectives" />
        </div>

        <div class="lesson-complete-row">
            <Form
                v-if="progress.status !== 'completed'"
                v-bind="complete.form(lesson.lesson_id)"
                v-slot="{ processing }"
            >
                <Button type="submit" :disabled="processing">
                    <CircleCheck class="size-4" />
                    {{ trans('Als erledigt markieren') }}
                </Button>
            </Form>
            <Form
                v-else
                v-bind="reopen.form(lesson.lesson_id)"
                v-slot="{ processing }"
            >
                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="processing"
                >
                    {{ trans('Als offen markieren') }}
                </Button>
            </Form>
            <span
                v-if="progress.status === 'completed'"
                class="lesson-complete-badge"
            >
                <CircleCheck class="size-4" />
                {{ trans('Erledigt') }}
            </span>
        </div>

        <LessonNavigation :prev="lesson.prev" :next="lesson.next" />
    </LessonLayout>
</template>
