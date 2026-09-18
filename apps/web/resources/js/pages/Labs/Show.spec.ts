import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
    Link: {
        props: ['href'],
        render(this: { $slots: Record<string, () => unknown>; href: unknown }) {
            // Wayfinder-Routen sind {url, method}-Objekte, keine reinen
            // Strings -- die echte Inertia-`<Link>` loest das selbst auf,
            // dieser Stub bildet nur exakt das nach.
            const href =
                typeof this.href === 'string'
                    ? this.href
                    : (this.href as { url: string }).url;

            return h('a', { href }, this.$slots.default?.() as never);
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
    next_step: {
        type: 'labs_index' as const,
        lesson_id: null,
        lesson_title: null,
    },
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

function fakeResponse(status: number, body: unknown = {}): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    } as Response;
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

        const { wrapper, onCommand } = mountShow();
        const result = await onCommand('echoscu 127.0.0.1 4242 -aec ORTHANC');

        expect(showAchievementUnlockToastsMock).toHaveBeenCalledExactlyOnceWith(
            unlocked,
        );
        expect(result).toEqual({ stdout: 'ok', stderr: '', exit_code: 0 });
        // PR #148, Prioritaet 4/5: der Solve wird sowohl live (aria-live)
        // als auch im sichtbaren Abschluss-Bereich angesagt/gezeigt.
        expect(wrapper.find('[role="status"]').text()).toContain(
            'Lab abgeschlossen. 20 Punkte erhalten.',
        );
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('Lab abgeschlossen');
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

/**
 * Sicherheitsnetz vor der `useRuntimeSession`-Extraktion (PR #148): fixiert
 * die heutige Zuordnung Props -> sichtbarer Zustand, bevor Show.vue seine
 * Polling-/Status-Logik auf den mit SandboxPanel.vue geteilten Composable
 * umstellt. Muss vor UND nach der mechanischen Extraktion unveraendert
 * gruen bleiben.
 */
describe('Labs/Show runtime state rendering', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows a start button when there is no attempt yet', () => {
        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                attempt: null,
                runtime: null,
                assertions: [],
            },
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('Lab starten');
        expect(wrapper.text()).not.toContain('Erfolgskriterien');
    });

    it('offers a retry button and shows the mapped error message once the runtime is unreachable', () => {
        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                runtime: {
                    status: 'sandbox_unavailable',
                    queue_position: null,
                },
            },
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('Erneut versuchen');
        expect(wrapper.text()).toContain(
            'Die Runtime-Umgebung ist gerade nicht erreichbar',
        );
    });

    it('shows the queue position while the runtime is queued', () => {
        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                runtime: { status: 'queued', queue_position: 4 },
                assertions: [],
            },
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('In der Warteschlange, Platz 4');
    });

    it('shows the assertion checklist and the terminal while running', () => {
        const wrapper = mount(Show, {
            props: baseProps,
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('Erfolgskriterien');
        expect(wrapper.findComponent(EngineTerminal).exists()).toBe(true);
    });

    it('shows the completion area with points and a catalog fallback link once solved', () => {
        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                attempt: { status: 'solved' as const },
            },
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('Lab abgeschlossen');
        expect(wrapper.text()).toContain('20 Punkte erhalten');
        const link = wrapper.find('a');
        expect(link.text()).toContain('Alle Labs');
        expect(link.attributes('href')).toBe('/de/labs');
        // Betreiber-Entscheidung (LabController::destroyRuntime()-Doc): kein
        // Neustart-Button mehr, sobald geloest.
        expect(wrapper.text()).not.toContain('Runtime neu starten');
    });

    it('links back to the owning lesson when one is known', () => {
        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                attempt: { status: 'solved' as const },
                next_step: {
                    type: 'lesson' as const,
                    lesson_id: '1.6',
                    lesson_title: 'Erste Verbindung',
                },
            },
            global: { stubs: { EngineTerminal: true } },
        });

        const link = wrapper.find('a');
        expect(link.text()).toContain('Zurück zur Lektion Erste Verbindung');
        expect(link.attributes('href')).toBe('/de/lessons/1.6');
    });

    it('labels an in-progress attempt with the unified status terminology', () => {
        const wrapper = mount(Show, {
            props: baseProps,
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('In Bearbeitung');
        expect(wrapper.text()).not.toContain('Begonnen');
    });

    it('switches from queued to running once polling reports the runtime is ready', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValueOnce(
            fakeResponse(200, { status: 'running', queue_position: null }),
        );

        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                runtime: { status: 'queued', queue_position: 1 },
                assertions: [],
            },
            global: { stubs: { EngineTerminal: true } },
        });

        expect(wrapper.text()).toContain('In der Warteschlange, Platz 1');

        await vi.advanceTimersByTimeAsync(3000);

        expect(wrapper.text()).not.toContain('In der Warteschlange');
        expect(wrapper.findComponent(EngineTerminal).exists()).toBe(true);

        vi.useRealTimers();
    });

    it('announces the runtime status via the sr-only live region once it changes', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValueOnce(
            fakeResponse(200, { status: 'running', queue_position: null }),
        );

        const wrapper = mount(Show, {
            props: {
                ...baseProps,
                runtime: { status: 'queued', queue_position: 2 },
                assertions: [],
            },
            global: { stubs: { EngineTerminal: true } },
        });

        // Keine Ansage fuer einen bereits beim Laden bekannten Zustand --
        // nur fuer eine tatsaechliche, live erlebte Aenderung.
        expect(wrapper.find('[role="status"]').text()).toBe('');

        await vi.advanceTimersByTimeAsync(3000);

        expect(wrapper.find('[role="status"]').text()).toContain(
            'Runtime bereit.',
        );

        vi.useRealTimers();
    });

    it('moves focus into the terminal once the runtime transitions to running', async () => {
        vi.useFakeTimers();
        const focus = vi.fn();
        fetchMock.mockResolvedValueOnce(
            fakeResponse(200, { status: 'running', queue_position: null }),
        );

        mount(Show, {
            props: {
                ...baseProps,
                runtime: { status: 'queued', queue_position: 1 },
                assertions: [],
            },
            global: {
                stubs: {
                    EngineTerminal: { template: '<div />', methods: { focus } },
                },
            },
        });

        expect(focus).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(3000);

        expect(focus).toHaveBeenCalledOnce();

        vi.useRealTimers();
    });

    it('does not steal focus when the runtime is already running on initial load', async () => {
        const focus = vi.fn();

        mount(Show, {
            props: baseProps,
            global: {
                stubs: {
                    EngineTerminal: { template: '<div />', methods: { focus } },
                },
            },
        });

        await Promise.resolve();

        expect(focus).not.toHaveBeenCalled();
    });
});
