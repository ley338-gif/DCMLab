import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { Achievement } from '@/types/achievement';
import AchievementBadge from './AchievementBadge.vue';

const unlockedAchievement: Achievement = {
    slug: 'first-blood',
    name: 'First Blood',
    description: 'Löse deinen ersten Lab- oder Node.',
    image: '/images/achievements/first-blood.png',
    category: 'labs',
    rarity: 'common',
    unlocked: true,
    unlocked_at: '2026-09-14T14:23:16+00:00',
};

const lockedAchievement: Achievement = {
    ...unlockedAchievement,
    slug: 'sandbox-starter',
    name: 'Sandbox Starter',
    image: '/images/achievements/sandbox-starter.png',
    unlocked: false,
    unlocked_at: null,
};

describe('AchievementBadge', () => {
    it('renders the unlocked state without the locked filter or lock icon', () => {
        const wrapper = mount(AchievementBadge, {
            props: { achievement: unlockedAchievement },
        });

        const img = wrapper.get('img');
        expect(img.classes()).not.toContain('achievement-badge-locked');
        expect(wrapper.find('svg').exists()).toBe(false);
        expect(wrapper.text()).toContain('First Blood');
    });

    it('renders the locked state with the grayscale filter class and a lock indicator', () => {
        const wrapper = mount(AchievementBadge, {
            props: { achievement: lockedAchievement },
        });

        const img = wrapper.get('img');
        expect(img.classes()).toContain('achievement-badge-locked');
        expect(wrapper.find('svg').exists()).toBe(true);
    });

    it('uses the achievement image and a status-aware alt text', () => {
        const unlockedWrapper = mount(AchievementBadge, {
            props: { achievement: unlockedAchievement },
        });
        const lockedWrapper = mount(AchievementBadge, {
            props: { achievement: lockedAchievement },
        });

        expect(unlockedWrapper.get('img').attributes('src')).toBe(
            '/images/achievements/first-blood.png',
        );
        expect(unlockedWrapper.get('img').attributes('alt')).toContain(
            'First Blood',
        );
        expect(lockedWrapper.get('img').attributes('alt')).toContain(
            'Sandbox Starter',
        );
    });

    it('respects the locked prop override regardless of achievement.unlocked', () => {
        const wrapper = mount(AchievementBadge, {
            props: { achievement: unlockedAchievement, locked: true },
        });

        expect(wrapper.get('img').classes()).toContain(
            'achievement-badge-locked',
        );
    });

    it.each([
        ['sm', 80],
        ['md', 110],
        ['lg', 180],
    ] as const)('renders the %s size at %dpx', (size, expectedPx) => {
        const wrapper = mount(AchievementBadge, {
            props: { achievement: unlockedAchievement, size },
        });

        expect(wrapper.get('img').attributes('width')).toBe(String(expectedPx));
    });

    it('hides the name label when showName is false', () => {
        const wrapper = mount(AchievementBadge, {
            props: { achievement: unlockedAchievement, showName: false },
        });

        expect(wrapper.text()).not.toContain('First Blood');
    });
});
