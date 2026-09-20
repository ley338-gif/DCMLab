import { flushPromises, mount } from '@vue/test-utils';
import { h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Achievement } from '@/types/achievement';
import Show from './Show.vue';

// Head/Link brauchen einen echten Inertia-App-Kontext, den ein isolierter
// Komponententest nicht aufbaut -- durch einfache Platzhalter ersetzt, wie
// schon in Labs/Show.spec.ts.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    Head: { render: () => null },
    Link: {
        props: ['href'],
        render(this: { $slots: Record<string, () => unknown>; href: unknown }) {
            const href =
                typeof this.href === 'string'
                    ? this.href
                    : (this.href as { url: string }).url;

            return h('a', { href }, this.$slots.default?.() as never);
        },
    },
}));

const postJsonMock = vi.fn();
vi.mock('@/lib/api', () => ({
    postJson: (...args: unknown[]) => postJsonMock(...args),
    deleteJson: vi.fn(),
}));

const showAchievementUnlockToastsMock = vi.fn();
vi.mock('@/lib/achievementToast', () => ({
    showAchievementUnlockToasts: (...args: unknown[]) =>
        showAchievementUnlockToastsMock(...args),
}));

function fakeResponse(status: number, body: unknown = {}): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    } as Response;
}

function achievement(overrides: Partial<Achievement> = {}): Achievement {
    return {
        slug: 'c-echo-badge',
        name: 'C-ECHO',
        description: 'Löse ein C-ECHO-Lab.',
        image: '/images/achievements/c-echo-badge.png',
        category: 'labs',
        rarity: 'common',
        unlocked: true,
        unlocked_at: '2026-09-17T00:00:00Z',
        ...overrides,
    };
}

const baseProps = {
    node: {
        slug: 'gefiltert',
        title: 'Gefiltert',
        scenario_title: 'Gefiltert',
        difficulty: 'hard',
        points: 50,
        category: 'pacs-operations',
        interaction: 'terminal',
        estimated_minutes: 30,
    },
    prev: null,
    next: null,
    briefing_html: '<p>Briefing</p>',
    hints: [],
    write_up_html: null,
    templates: [],
    placeholders: [],
    state: {
        node_slug: 'gefiltert',
        hosts: {},
        hints_used: [],
        write_up_seen: false,
        solved: false,
        points: 50,
        stuck: false,
    },
    attempt: { status: 'started' as const },
};

function mountShow() {
    return mount(Show, {
        props: baseProps,
        global: { stubs: { EngineTerminal: true } },
    });
}

function submitButton(wrapper: ReturnType<typeof mountShow>) {
    const button = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().includes('Flag prüfen'));

    if (!button) throw new Error('Flag-Submit-Button nicht gefunden');

    return button;
}

/**
 * Phase D.2: `correct` und `solved` sind seit dem Solve-Feedback-Umbau
 * getrennte Felder im `/flag`-Response -- diese Tests fixieren, dass die
 * UI die drei Faelle (falsch / korrekt-aber-unvollstaendig / geloest)
 * unterschiedlich behandelt, insbesondere dass "korrekt, aber unvollstaendig"
 * NICHT wie ein Solve aussieht oder behandelt wird.
 */
describe('Nodes/Show flag feedback (Phase D.2)', () => {
    beforeEach(() => {
        postJsonMock.mockReset();
        showAchievementUnlockToastsMock.mockReset();
        vi.stubGlobal('fetch', vi.fn());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows the existing wrong-answer message for an incorrect flag', async () => {
        postJsonMock.mockResolvedValue({
            correct: false,
            solved: false,
            unlocked_achievements: [],
        });

        const wrapper = mountShow();
        await wrapper.find('input').setValue('SR');
        await submitButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Leider falsch.');
        expect(wrapper.text()).not.toContain('Richtig!');
        expect(showAchievementUnlockToastsMock).not.toHaveBeenCalled();
        expect(fetch).not.toHaveBeenCalled();
    });

    it('shows a distinct neutral message for a correct-but-incomplete answer, without treating it as solved', async () => {
        postJsonMock.mockResolvedValue({
            correct: true,
            solved: false,
            reason: 'prerequisites_not_met',
            unlocked_achievements: [],
        });

        const wrapper = mountShow();
        await wrapper.find('input').setValue('PACS-TO-DOSE');
        await submitButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain(
            'Richtige Diagnose – der erforderliche Betriebszustand wurde im Lab noch nicht vollständig reproduziert.',
        );
        expect(wrapper.text()).not.toContain('Leider falsch.');
        // Kein Solve-Verhalten: kein State-Refetch, kein Write-up-Aufruf,
        // kein Achievement-Toast, `state.solved` bleibt unveraendert false.
        expect(fetch).not.toHaveBeenCalled();
        expect(postJsonMock).toHaveBeenCalledTimes(1);
        expect(showAchievementUnlockToastsMock).not.toHaveBeenCalled();
        expect(wrapper.text()).not.toContain('Gelöst');
    });

    it('keeps the existing solved UX (state refetch, write-up, achievement toasts) when solved is true', async () => {
        postJsonMock.mockImplementation((url: string) => {
            if (url.includes('/write-up')) {
                return Promise.resolve({ write_up_html: '<p>Die Lösung</p>' });
            }

            return Promise.resolve({
                correct: true,
                solved: true,
                points: 50,
                unlocked_achievements: [achievement()],
            });
        });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                fakeResponse(200, {
                    ...baseProps.state,
                    solved: true,
                    points: 50,
                }),
            ),
        );

        const wrapper = mountShow();
        await wrapper.find('input').setValue('PACS-TO-DOSE');
        await submitButton(wrapper).trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Richtig! 50 Punkte.');
        // Ein Aufruf aus submitFlag() selbst, ein weiterer aus dem
        // anschliessenden viewWriteUp() -- unveraendertes Bestandsverhalten.
        expect(fetch).toHaveBeenCalledTimes(2);
        expect(showAchievementUnlockToastsMock).toHaveBeenCalledExactlyOnceWith(
            [achievement()],
        );
    });
});
