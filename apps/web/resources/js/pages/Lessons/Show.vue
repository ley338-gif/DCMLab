<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck, FlaskConical } from '@lucide/vue';
import { computed, ref } from 'vue';
import LessonHero from '@/components/lesson/LessonHero.vue';
import LessonSummary from '@/components/lesson/LessonSummary.vue';
import LessonToc from '@/components/lesson/LessonToc.vue';
import LearningObjectives from '@/components/lesson/LearningObjectives.vue';
import LabCard from '@/components/lesson/LabCard.vue';
import PracticeTask from '@/components/lesson/PracticeTask.vue';
import ToolGrid from '@/components/lesson/ToolGrid.vue';
import TrackSidebar, {
    type SidebarLesson,
    type SidebarTrackSummary,
} from '@/components/lesson/TrackSidebar.vue';
import PreviousNextNavigation, {
    type NavNeighbor,
} from '@/components/PreviousNextNavigation.vue';
import QuizSection, {
    type QuizQuestion,
} from '@/components/quiz/QuizSection.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useLessonProseEnhancements } from '@/composables/useLessonProseEnhancements';
import { useLessonToc } from '@/composables/useLessonToc';
import LessonLayout from '@/layouts/lesson/LessonLayout.vue';
import { trans } from '@/lib/trans';
import { complete, reopen, show as showLesson } from '@/routes/lessons';

type ToolbarTool = {
    slug: string;
    name: string;
    purpose: string | null;
    example: string | null;
    is_new: boolean;
};

type ToolbarData = {
    tools: ToolbarTool[];
    requires: { lesson_id: string | null; title: string; completed: boolean }[];
    prerequisites_met: boolean;
    related_node_optional: boolean;
};

type RelatedNode = {
    slug: string;
    title: string;
    difficulty: string;
    points: number;
};

type LabSummary = {
    slug: string;
    title: string;
    estimated_minutes: number;
    status: string;
};

type SandboxDataset = { note: string | null; file_count: number | null };

// ADR 0105 (CMS-6b): die geordnete Elementsequenz einer Lektion -- WELCHE
// Art Element es ist, steht in `type`, nicht mehr in einer fest
// verdrahteten Body/Sandbox/RelatedNode/Quiz-Abfolge im Template.
type LessonElement =
    | { type: 'content'; body_html: string }
    | { type: 'sandbox'; dataset: SandboxDataset | null }
    | { type: 'related_node'; related_node: RelatedNode | null }
    | { type: 'lab'; lab: LabSummary | null }
    | { type: 'quiz'; questions: QuizQuestion[] };

type NeighborLesson = { lesson_id: string; title: string } | null;

const props = defineProps<{
    lesson: {
        lesson_id: string;
        title: string;
        teaser: string;
        objectives: string[];
        duration_minutes: number;
        level: string;
        position_in_track: number | null;
        track_lessons_count: number;
        prev: NeighborLesson;
        next: NeighborLesson;
    };
    track: { slug: string; title_key: string };
    elements: LessonElement[];
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
    // Draft-Lesson-Vorschau (analog Node, ADR 0110/0119): serverseitig
    // ermittelt (`LearnerViewBuilder::lessonProps()`), niemals aus einem
    // Query-Parameter oder Client-Zustand ableitbar. Gilt sowohl fuer die
    // autorisierte, echte Vorschau (`LessonController::show()`) als auch
    // fuer die editorielle Copy-Vorschau (`LessonEditorController::
    // preview()`) -- beide bedeuten fuer den Betrachter dasselbe: Ergebnisse
    // und Fortschritt werden nicht gewertet.
    draft_preview: boolean;
}>();

const contentRef = ref<HTMLElement | null>(null);
// useLessonToc muss nur wissen, WANN sich der Seiteninhalt geaendert hat
// (Lektionswechsel) -- das Content-Element aendert sich dabei immer mit.
const bodyHtml = computed(
    () =>
        props.elements.find(
            (element): element is Extract<LessonElement, { type: 'content' }> =>
                element.type === 'content',
        )?.body_html ?? '',
);

const { entries, activeId, scrollToEntry } = useLessonToc(contentRef, bodyHtml);
useLessonProseEnhancements(contentRef);

function toNavNeighbor(neighbor: NeighborLesson, label: string): NavNeighbor {
    return neighbor
        ? {
              href: showLesson(neighbor.lesson_id).url,
              label,
              title: neighbor.title,
          }
        : null;
}

const prevNav = computed(() =>
    toNavNeighbor(props.lesson.prev, trans('Vorherige Lektion')),
);
const nextNav = computed(() =>
    toNavNeighbor(props.lesson.next, trans('Nächste Lektion')),
);
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

        <Alert v-if="draft_preview" class="mb-6">
            <FlaskConical class="size-4" aria-hidden="true" />
            <AlertTitle>{{ trans('Entwurfsvorschau') }}</AlertTitle>
            <AlertDescription>
                {{ trans('Ergebnisse und Fortschritt werden nicht gewertet.') }}
            </AlertDescription>
        </Alert>

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

            <!-- ADR 0105 (CMS-6b): die Reihenfolge kommt aus
                 lesson_elements, nicht mehr aus einer festen Abfolge hier. -->
            <template v-for="(element, index) in elements" :key="index">
                <div
                    v-if="element.type === 'content'"
                    class="lesson-prose"
                    v-html="element.body_html"
                />
                <PracticeTask
                    v-else-if="element.type === 'sandbox'"
                    :lesson-id="lesson.lesson_id"
                    :needs-sandbox="true"
                    :dataset="element.dataset"
                    :related-node="null"
                />
                <PracticeTask
                    v-else-if="element.type === 'related_node'"
                    :lesson-id="lesson.lesson_id"
                    :needs-sandbox="false"
                    :dataset="null"
                    :related-node="element.related_node"
                />
                <LabCard
                    v-else-if="element.type === 'lab'"
                    :lab="element.lab"
                />
                <QuizSection
                    v-else-if="element.type === 'quiz'"
                    :lesson-id="lesson.lesson_id"
                    :questions="element.questions"
                />
            </template>

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

        <PreviousNextNavigation :prev="prevNav" :next="nextNav" />
    </LessonLayout>
</template>
