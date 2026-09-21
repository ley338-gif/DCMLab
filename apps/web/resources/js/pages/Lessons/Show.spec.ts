import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import Show from './Show.vue';

// Head/Form brauchen einen echten Inertia-App-Kontext, den ein isolierter
// Komponententest nicht aufbaut -- durch einfache Platzhalter ersetzt, wie
// schon in Labs/Show.spec.ts und Nodes/Show.spec.ts.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    Head: { render: () => null },
    Form: {
        render(this: { $slots: Record<string, (scope: unknown) => unknown> }) {
            return h(
                'form',
                {},
                this.$slots.default?.({ processing: false }) as never,
            );
        },
    },
}));

const baseProps = {
    lesson: {
        lesson_id: '1.0',
        title: 'Testlektion',
        teaser: 'Teaser',
        objectives: [],
        duration_minutes: 10,
        level: 'einsteiger',
        position_in_track: 1,
        track_lessons_count: 5,
        prev: null,
        next: null,
    },
    track: { slug: 'fundamente', title_key: 'Fundamente' },
    elements: [],
    toolbar: {
        tools: [],
        requires: [],
        prerequisites_met: true,
        related_node_optional: false,
    },
    progress: { status: 'started', is_returning_visit: false },
    sidebar: {
        current_track: {
            slug: 'fundamente',
            title_key: 'Fundamente',
            lessons: [],
        },
        other_tracks: [],
        overall: { completed: 0, total: 0 },
    },
};

function mountShow(propOverrides: Record<string, unknown> = {}) {
    // Nur LessonLayout (braucht GlobalHeader/Inertia-Kontext) und die
    // schweren, fuer diesen Test irrelevanten Lesson-Elemente werden
    // gestubbt -- Alert/AlertTitle/AlertDescription bleiben echt, weil
    // genau die hier geprueft werden.
    return mount(Show, {
        props: { ...baseProps, ...propOverrides },
        global: {
            stubs: {
                LessonLayout: {
                    template:
                        '<div><slot name="sidebar" /><slot name="toc" /><slot /></div>',
                },
                LessonHero: true,
                LessonSummary: true,
                LessonToc: true,
                LearningObjectives: true,
                LabCard: true,
                PracticeTask: true,
                ToolGrid: true,
                TrackSidebar: true,
                PreviousNextNavigation: true,
                QuizSection: true,
            },
        },
    });
}

/**
 * Draft-Lesson-Vorschau (analog Node, ADR 0110/0119): `draft_preview` ist
 * ein serverseitig ermitteltes Prop (`LearnerViewBuilder::lessonProps()`),
 * das sowohl bei der autorisierten echten Vorschau
 * (`LessonController::show()`) als auch bei der editoriellen Copy-Vorschau
 * (`LessonEditorController::preview()`) dasselbe Banner zeigt -- nie bei
 * einer regulaer veroeffentlichten Lesson.
 */
describe('Lessons/Show draft-preview banner', () => {
    it('shows the draft-preview banner when draft_preview is true', () => {
        const wrapper = mountShow({ draft_preview: true });

        expect(wrapper.text()).toContain('Entwurfsvorschau');
        expect(wrapper.text()).toContain(
            'Ergebnisse und Fortschritt werden nicht gewertet.',
        );
    });

    it('does not show the draft-preview banner for a regular published lesson', () => {
        const wrapper = mountShow({ draft_preview: false });

        expect(wrapper.text()).not.toContain('Entwurfsvorschau');
    });
});
