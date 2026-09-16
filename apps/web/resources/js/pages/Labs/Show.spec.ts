import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import EngineTerminal from '@/components/EngineTerminal.vue';
import type { Achievement } from '@/types/achievement';
import Show from './Show.vue';

// Head/Form brauchen einen echten Inertia-App-Kontext (Head-Manager,
// Router, CSRF), den ein isolierter Komponententest nicht aufbaut -- durch
// einfache Platzhalter ersetzt, wie schon in Dashboard.spec.ts fuer Link.
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

/**
 * Betreiber-Korrektur (CMS-8d, Haerten): ein direkter Aufruf von
 * `POST .../exec` beweist nur die Backend-Seite -- ob `runCommand()`
 * tatsaechlich `showAchievementUnlockToasts()` aufruft, laesst sich nur
 * feststellen, wenn dieser Test der EngineTerminal-`onCommand`-Prop selbst
 * aufruft, unabhaengig davon, ob echte Tastatureingaben je xterm.js
 * erreichen (Browser-Automation-Einschraenkung, siehe Plan).
 */
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
    lab: {
        slug: 'c-echo-connectivity',
        title: 'C-ECHO Connectivity',
        scenario_title: '',
        difficulty: 'easy',
        points: 20,
        estimated_minutes: 15,
    },
    briefing_html: null,
    attempt: { status: 'started' as const },
    can_start: true,
    runtime: { status: 'running' as const, queue_position: null },
    assertions: [{ index: 0, type: 'command_executed', passed: false }],
    runtime_error: null,
};

function mountShow() {
    // EngineTerminal instantiates a real xterm.js Terminal in onMounted()
    // (canvas/matchMedia), neither of which jsdom implements -- stubbed
    // out entirely since only its `onCommand` prop is under test here,
    // never its actual rendering.
    const wrapper = mount(Show, {
        props: baseProps,
        global: { stubs: { EngineTerminal: true } },
    });
    const onCommand = wrapper
        .findComponent(EngineTerminal)
        .props('onCommand') as (
        command: string,
    ) => Promise<{ stdout: string; stderr: string; exit_code: number }>;

    return { wrapper, onCommand };
}

describe('Labs/Show runCommand achievement wiring', () => {
    beforeEach(() => {
        postJsonMock.mockReset();
        showAchievementUnlockToastsMock.mockReset();
    });

    it('shows a toast for every achievement unlocked by a satisfying exec() response', async () => {
        const unlocked = [achievement()];
        postJsonMock.mockResolvedValue({
            stdout: 'ok',
            stderr: '',
            exit_code: 0,
            assertions: [{ index: 0, type: 'command_executed', passed: true }],
            all_satisfied: true,
            unlocked_achievements: unlocked,
        });

        const { onCommand } = mountShow();
        const result = await onCommand('echoscu 127.0.0.1 4242 -aec ORTHANC');

        expect(showAchievementUnlockToastsMock).toHaveBeenCalledExactlyOnceWith(
            unlocked,
        );
        expect(result).toEqual({ stdout: 'ok', stderr: '', exit_code: 0 });
    });

    it('calls showAchievementUnlockToasts with an empty list when nothing unlocked', async () => {
        postJsonMock.mockResolvedValue({
            stdout: '',
            stderr: 'not found',
            exit_code: 1,
            assertions: [{ index: 0, type: 'command_executed', passed: false }],
            all_satisfied: false,
            unlocked_achievements: [],
        });

        const { onCommand } = mountShow();
        await onCommand('dcmdump foo.dcm');

        expect(showAchievementUnlockToastsMock).toHaveBeenCalledExactlyOnceWith(
            [],
        );
    });
});
