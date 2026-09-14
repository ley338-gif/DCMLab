import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import type { Achievement } from '@/types/achievement';
import Dashboard from './Dashboard.vue';

// Head/Link brauchen einen echten Inertia-App-Kontext (Head-Manager,
// Router), den ein isolierter Komponententest nicht aufbaut -- durch
// einfache Platzhalter ersetzt, weil hier nur die Achievements-Sektion
// interessiert.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    Head: { render: () => null },
    Link: {
        render(this: { $slots: Record<string, () => unknown> }) {
            return h('a', {}, this.$slots.default?.() as never);
        },
    },
}));

const baseProps = {
    profile: { points: 0, rank: 'novice', skill_vector: {} },
    tracks: [],
    recent_lessons: [],
    pioneer_achievements: [],
    due_reviews_count: 0,
};

function achievement(overrides: Partial<Achievement>): Achievement {
    return {
        slug: 'first-blood',
        name: 'First Blood',
        description: 'Löse deinen ersten Lab- oder Node.',
        image: '/images/achievements/first-blood.png',
        category: 'labs',
        rarity: 'common',
        unlocked: false,
        unlocked_at: null,
        ...overrides,
    };
}

function mountDashboard(achievements: Achievement[]) {
    return mount(Dashboard, {
        props: { ...baseProps, achievements },
    });
}

describe('Dashboard achievements section', () => {
    it('shows a placeholder when there are no achievements at all', () => {
        const wrapper = mountDashboard([]);

        expect(wrapper.text()).toContain('Noch keine Achievements verfügbar.');
        expect(wrapper.findAllComponents(AchievementBadge)).toHaveLength(0);
    });

    it('renders a badge per achievement and the unlocked/total count', () => {
        const achievements = [
            achievement({
                slug: 'first-blood',
                unlocked: true,
                unlocked_at: '2026-09-14T00:00:00Z',
            }),
            achievement({ slug: 'sandbox-starter', unlocked: false }),
            achievement({
                slug: 'echo-heard',
                unlocked: true,
                unlocked_at: '2026-09-10T00:00:00Z',
            }),
        ];

        const wrapper = mountDashboard(achievements);

        expect(wrapper.findAllComponents(AchievementBadge)).toHaveLength(3);
        expect(wrapper.text()).toContain('2 / 3');
    });
});
