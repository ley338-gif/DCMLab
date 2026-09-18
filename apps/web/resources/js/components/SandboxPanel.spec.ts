import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SandboxPanel from './SandboxPanel.vue';

/**
 * Sicherheitsnetz vor der `useRuntimeSession`-Extraktion (PR #148): fixiert
 * das heutige, hand-verdrahtete Verhalten von SandboxPanel.vue, bevor seine
 * Start/Poll/Fehler-Zustandsmaschine auf den mit Labs/Show.vue geteilten
 * Composable umgestellt wird. Muss vor UND nach der mechanischen Extraktion
 * unveraendert gruen bleiben.
 */
vi.mock('@/lib/achievementToast', () => ({
    showAchievementUnlockToasts: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
    postJson: vi.fn(),
    deleteJson: vi.fn().mockResolvedValue(undefined),
}));

function fakeResponse(status: number, body: unknown = {}): Response {
    return {
        ok: status >= 200 && status < 300,
        status,
        json: () => Promise.resolve(body),
    } as Response;
}

function mountPanel() {
    // EngineTerminal instanziiert ein echtes xterm.js-Terminal (Canvas/
    // matchMedia), das jsdom nicht implementiert -- wie in Labs/Show.spec.ts
    // nur als Stub gemountet, nur der Rest der Zustandsmaschine steht hier
    // unter Test.
    return mount(SandboxPanel, {
        props: { lessonId: '1.6' },
        global: { stubs: { EngineTerminal: true } },
    });
}

describe('SandboxPanel', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows the start button while idle', () => {
        const wrapper = mountPanel();

        expect(wrapper.text()).toContain('Spielwiese starten');
    });

    it('shows the terminal once the runtime starts immediately as running', async () => {
        fetchMock.mockResolvedValueOnce(
            fakeResponse(201, {
                status: 'running',
                sandbox_id: 'sb-1',
                queue_position: null,
                unlocked_achievements: [],
            }),
        );

        const wrapper = mountPanel();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Spielwiese beenden');
        expect(wrapper.text()).not.toContain('Spielwiese starten');
    });

    it('shows the queue position while a runtime request is queued', async () => {
        fetchMock.mockResolvedValueOnce(
            fakeResponse(201, {
                status: 'queued',
                sandbox_id: 'sb-1',
                queue_position: 3,
                unlocked_achievements: [],
            }),
        );

        const wrapper = mountPanel();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('In der Warteschlange, Platz 3');
    });

    it('switches from queued to running once polling reports the runtime is ready', async () => {
        vi.useFakeTimers();
        fetchMock
            .mockResolvedValueOnce(
                fakeResponse(201, {
                    status: 'queued',
                    sandbox_id: 'sb-1',
                    queue_position: 2,
                    unlocked_achievements: [],
                }),
            )
            .mockResolvedValueOnce(
                fakeResponse(200, { status: 'running', queue_position: null }),
            );

        const wrapper = mountPanel();
        await wrapper.find('button').trigger('click');
        await vi.advanceTimersByTimeAsync(0);

        expect(wrapper.text()).toContain('Platz 2');

        await vi.advanceTimersByTimeAsync(3000);

        expect(wrapper.text()).toContain('Spielwiese beenden');

        vi.useRealTimers();
    });

    it('shows a distinct message when the daily quota is exhausted (429)', async () => {
        fetchMock.mockResolvedValueOnce(fakeResponse(429));

        const wrapper = mountPanel();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Tageskontingent');
        expect(wrapper.find('button').text()).toContain('Spielwiese starten');
    });

    it('shows a generic error message for any other failed start', async () => {
        fetchMock.mockResolvedValueOnce(fakeResponse(503));

        const wrapper = mountPanel();
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('gerade nicht erreichbar');
    });
});
