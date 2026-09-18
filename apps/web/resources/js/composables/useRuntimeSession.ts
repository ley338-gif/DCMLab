import { onBeforeUnmount, onMounted, ref } from 'vue';

export type RuntimeStatus =
    | 'idle'
    | 'starting'
    | 'queued'
    | 'running'
    | 'error';

type RuntimeStateResponse = {
    status: 'queued' | 'running';
    queue_position: number | null;
};

const POLL_INTERVAL_MS = 3000;

/**
 * Gemeinsame Runtime-Zustandsmaschine fuer die Spielwiese (SandboxPanel.vue)
 * und ein Lab (Labs/Show.vue): beide pollen denselben Automaten
 * (idle/starting/queued/running/error) im selben 3s-Takt -- nur die
 * Fetch-Quelle fuer den Runtime-Status unterscheidet sich (sandboxId- vs.
 * lab-slug-basiert, siehe `fetchState`). Exec/Destroy bleiben bewusst bei
 * den Aufrufern, da sie sich fachlich unterscheiden (Achievements/
 * Assertions/Attempt-Status nur bei Lab).
 *
 * PR #148: mechanische Extraktion aus den beiden vorher unabhaengig
 * hand-verdrahteten Kopien -- siehe SandboxPanel.spec.ts/Show.spec.ts fuer
 * das Verhaltens-Sicherheitsnetz, das diese Extraktion abdeckt.
 */
export function useRuntimeSession(
    fetchState: () => Promise<RuntimeStateResponse>,
    initialStatus: RuntimeStatus = 'idle',
    initialQueuePosition: number | null = null,
) {
    const status = ref<RuntimeStatus>(initialStatus);
    const queuePosition = ref<number | null>(initialQueuePosition);
    const errorMessage = ref<string | null>(null);

    let pollTimer: ReturnType<typeof setInterval> | null = null;

    function stopPolling() {
        if (pollTimer !== null) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    /**
     * Polling muss auf JEDEM Ausstiegspfad enden -- geworden zu `running`,
     * eine nicht-ok-Antwort, das Unmounten der Komponente und ein manuelles
     * Beenden. Sonst laeuft nach einem Reap ein verstecktes `setInterval()`
     * unbemerkt weiter.
     */
    async function poll() {
        try {
            const state = await fetchState();

            if (state.status === 'running') {
                stopPolling();
                status.value = 'running';
            } else {
                queuePosition.value = state.queue_position;
            }
        } catch {
            stopPolling();
            status.value = 'error';
            errorMessage.value = 'sandbox_unavailable';
        }
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(poll, POLL_INTERVAL_MS);
    }

    function enterStarting() {
        stopPolling();
        status.value = 'starting';
        errorMessage.value = null;
    }

    function enterQueued(position: number | null) {
        status.value = 'queued';
        queuePosition.value = position;
        startPolling();
    }

    function enterRunning() {
        stopPolling();
        status.value = 'running';
    }

    function enterError(reason: string | null = 'sandbox_unavailable') {
        stopPolling();
        status.value = 'error';
        errorMessage.value = reason;
    }

    function enterIdle() {
        stopPolling();
        status.value = 'idle';
        queuePosition.value = null;
        errorMessage.value = null;
    }

    // Ein Reload-persistierter `queued`-Zustand (Lab: current_sandbox_
    // session_id ueberlebt den Reload, siehe Show.vue) muss sein Polling
    // selbst wieder aufnehmen -- SandboxPanel startet dagegen immer als
    // `idle`, hier also ein No-op. Wie zuvor bewusst erst in `onMounted`,
    // nicht schon waehrend `setup()`.
    onMounted(() => {
        if (status.value === 'queued') {
            startPolling();
        }
    });

    onBeforeUnmount(stopPolling);

    return {
        status,
        queuePosition,
        errorMessage,
        enterStarting,
        enterQueued,
        enterRunning,
        enterError,
        enterIdle,
        stopPolling,
    };
}
