import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import LessonHero from './LessonHero.vue';

const baseProps = {
    track: { slug: 'fundamente', title_key: 'Fundamente' },
    title: 'Testlektion',
    teaser: 'Teaser',
    durationMinutes: 10,
    level: 'einsteiger',
    objectivesCount: 2,
    positionInTrack: 2,
    trackLessonsCount: 5,
};

/**
 * Published Content Boundary Hardening (Audit-Befund A): eine
 * unveroeffentlichte Voraussetzung kommt vom Server bereits maskiert
 * (`lesson_id: null`, generischer Platzhalter-Titel, siehe
 * LearnerViewBuilder::toolbarData()) -- diese Tests belegen, dass die
 * Oberflaeche daraus keinen echten Link baut, waehrend eine sichtbare
 * Voraussetzung weiterhin verlinkt bleibt.
 */
describe('LessonHero requires rendering', () => {
    it('renders a masked (unmet) requirement as plain text without a link', () => {
        const wrapper = mount(LessonHero, {
            props: {
                ...baseProps,
                requires: [
                    {
                        lesson_id: null,
                        title: 'Bald verfügbar',
                        completed: false,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Bald verfügbar');
        expect(wrapper.findAll('a')).toHaveLength(2); // nur die beiden Breadcrumb-Links
    });

    it('still links a real, unmet requirement the viewer is allowed to see', () => {
        const wrapper = mount(LessonHero, {
            props: {
                ...baseProps,
                requires: [
                    { lesson_id: '1.0', title: 'Grundlagen', completed: false },
                ],
            },
        });

        const requirementLink = wrapper
            .findAll('a')
            .find((a) => a.text() === 'Grundlagen');

        expect(requirementLink).toBeDefined();
    });

    it('renders an already-met requirement without a locked notice', () => {
        const wrapper = mount(LessonHero, {
            props: {
                ...baseProps,
                requires: [
                    { lesson_id: '1.0', title: 'Grundlagen', completed: true },
                ],
            },
        });

        expect(wrapper.find('.lesson-hero-locked-notice').exists()).toBe(false);
        expect(wrapper.text()).toContain('Grundlagen');
    });
});
