<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Loader2, Square } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { showAchievementUnlockToasts } from '@/lib/achievementToast';
import { deleteJson, postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { exec as execLab, start as startLab } from '@/routes/labs';
import {
    destroy as destroyRuntime,
    state as runtimeState,
} from '@/routes/labs/runtime';
import type { Achievement } from '@/types/achievement';

type AttemptStatus = 'started' | 'solved' | 'abandoned';

/**
 * Wie bei SandboxPanel.vue: `idle` (keine/keine mehr bekannte Runtime),
 * `starting` (Formular-Submit laeuft, von Inertias eigenem `processing`
 * abgedeckt -- hier nur der Vollstaendigkeit halber im Typ), `queued`,
 * `running`, `error` (Runtime nicht mehr erreichbar -- Polling/Exec haben
 * das festgestellt, nicht dasselbe wie `idle`, aber UI-seitig dieselbe
 * Reaktion: kein Terminal, ggf. ein Retry-Button).
 */
type RuntimeStatus = 'idle' | 'starting' | 'queued' | 'running' | 'error';

type LabProps = {
    slug: string;
    title: string;
    scenario_title: string;
    difficulty: string;
    points: number;
    estimated_minutes: number;
};

type AssertionState = {
    index: number;
    type: string;
    passed: boolean;
};

const props = defineProps<{
    lab: LabProps;
    briefing_html: string | null;
    attempt: { status: AttemptStatus } | null;
    can_start: boolean;
    runtime: {
        status: 'queued' | 'running' | 'sandbox_unavailable';
        queue_position: number | null;
    } | null;
    assertions: AssertionState[];
    // Betreiber-Korrektur: lokales Prop statt globalem Flash-Sharing --
    // start()s einziger Rückkanal ist der Redirect zurück auf show().
    runtime_error: string | null;
}>();

const runtimeErrorLabels: Record<string, string> = {
    active_runtime_exists: trans(
        'Du hast bereits eine andere praktische Umgebung geöffnet.',
    ),
    quota_exceeded: trans('Dein Runtime-Kontingent ist derzeit ausgeschöpft.'),
    lab_unavailable: trans(
        'Dieses Lab ist gerade nicht verfügbar. Bitte später erneut versuchen.',
    ),
    sandbox_unavailable: trans(
        'Die Runtime-Umgebung ist gerade nicht erreichbar. Bitte später erneut versuchen.',
    ),
};

// Betreiber-Vorgabe: ein unbekannter Fehlerschlüssel bekommt nur eine
// generische Meldung -- nie den rohen Schlüssel selbst anzeigen.
const genericRuntimeErrorLabel = trans(
    'Die Runtime konnte nicht gestartet werden. Bitte später erneut versuchen.',
);

const statusLabels: Record<AttemptStatus, string> = {
    started: trans('Begonnen'),
    solved: trans('Abgeschlossen'),
    abandoned: trans('Abgebrochen'),
};

// Betreiber-Vorgabe: keine fachlichen Details leaken -- nur ein
// generisches Label je Typ, nie der rohe Prefix.
const assertionTypeLabels: Record<string, string> = {
    command_executed: trans(
        'Ein passender Befehl wurde erfolgreich ausgeführt.',
    ),
};

function assertionLabel(assertion: AssertionState): string {
    const description =
        assertionTypeLabels[assertion.type] ??
        trans('Unbekanntes Erfolgskriterium.');

    return `${trans('Erfolgskriterium :n', { n: assertion.index + 1 })}: ${description}`;
}

/**
 * Von den Server-Props initialisiert (anders als bei der Spielwiese
 * verliert ein Lab seinen Zustand NICHT bei einem Reload -- current_
 * sandbox_session_id ist serverseitig persistiert, show() fragt die
 * Runtime deshalb live ab), danach lokal per Polling/Exec-Antworten
 * fortgeschrieben, ohne jedes Mal einen vollen Seiten-Reload zu brauchen.
 * Kein sandboxId-State -- jede Anfrage geht ausschließlich über den
 * Lab-Slug, nie über eine Sitzungs-ID.
 */
const attemptStatus = ref<AttemptStatus | null>(props.attempt?.status ?? null);
const runtimeStatus = ref<RuntimeStatus>(
    props.runtime === null
        ? 'idle'
        : props.runtime.status === 'sandbox_unavailable'
          ? 'error'
          : props.runtime.status,
);
const queuePosition = ref<number | null>(props.runtime?.queue_position ?? null);
const assertions = ref<AssertionState[]>(props.assertions);
const destroying = ref(false);

/**
 * Betreiber-Korrektur (Haerten): einzige Quelle fuer die sichtbare
 * Fehlermeldung -- start()s Session-Flash (`props.runtime_error`) ODER,
 * falls das initiale `show()` bereits eine nicht erreichbare Runtime
 * gemeldet hat, dieselbe Meldung von Anfang an. Jede spaetere, vom
 * Client selbst entdeckte Nichtverfuegbarkeit (Polling, exec(),
 * restartRuntime()) schreibt hierher, statt den Lernenden mit einem
 * erklaerungslosen Retry-Button allein zu lassen.
 */
const runtimeErrorMessage = ref<string | null>(
    props.runtime_error ??
        (props.runtime?.status === 'sandbox_unavailable'
            ? 'sandbox_unavailable'
            : null),
);

const startButtonLabel = computed(() => {
    if (!attemptStatus.value) {
        return trans('Lab starten');
    }

    return runtimeErrorMessage.value
        ? trans('Erneut versuchen')
        : trans('Runtime starten');
});

let pollTimer: ReturnType<typeof setInterval> | null = null;

function stopPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

/**
 * Polling muss auf JEDEM Ausstiegspfad enden -- geworden zu `running`,
 * eine 404/`gone`-Antwort, das Unmounten der Komponente (siehe
 * `onBeforeUnmount`) und ein manuelles Beenden (siehe `restartRuntime`).
 * Sonst laeuft nach einem Reap ein verstecktes `setInterval()` unbemerkt
 * weiter.
 */
async function pollRuntimeState() {
    const response = await fetch(runtimeState.url({ lab: props.lab.slug }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        stopPolling();
        runtimeStatus.value = 'error';
        runtimeErrorMessage.value = 'sandbox_unavailable';
        return;
    }

    const result = (await response.json()) as {
        status: 'queued' | 'running';
        queue_position: number | null;
    };

    if (result.status === 'running') {
        stopPolling();
        runtimeStatus.value = 'running';
    } else {
        queuePosition.value = result.queue_position;
    }
}

/**
 * `postJson()` wirft bei jeder Nicht-2xx-Antwort einen generischen Error
 * (kein Statuscode) -- `EngineTerminal` erwartet aber immer ein aufgelöstes
 * `{stdout, stderr, exit_code}`, nie eine Rejection. Ein Fehlschlag
 * bedeutet hier praktisch immer "Runtime nicht mehr erreichbar", also
 * derselbe `error`-Zustand wie bei einer verlorenen Runtime waehrend des
 * Pollings.
 */
async function runCommand(command: string) {
    try {
        const result = await postJson<{
            stdout: string;
            stderr: string;
            exit_code: number;
            assertions: AssertionState[];
            all_satisfied: boolean;
            unlocked_achievements: Achievement[];
        }>(execLab.url({ lab: props.lab.slug }), { command });

        assertions.value = result.assertions;

        if (result.all_satisfied) {
            attemptStatus.value = 'solved';
        }

        showAchievementUnlockToasts(result.unlocked_achievements);

        return {
            stdout: result.stdout,
            stderr: result.stderr,
            exit_code: result.exit_code,
        };
    } catch {
        stopPolling();
        runtimeStatus.value = 'error';
        runtimeErrorMessage.value = 'sandbox_unavailable';

        return {
            stdout: '',
            stderr: trans(
                'Die Runtime ist nicht mehr erreichbar. Bitte die Seite neu laden.',
            ),
            exit_code: 1,
        };
    }
}

/**
 * Kein versteckter Einzel-Aufruf fuer "Neustart" -- ganz bewusst erst
 * `destroyRuntime()` abwarten, dann einen neuen `start()`-Visit anstoßen
 * (derselbe Endpunkt wie der erste "Lab starten"-Klick, dieselbe
 * Idempotenz ueber `runtimeKey`). Nur solange der Attempt noch `started`
 * ist -- fuer `solved` bietet die UI diesen Button gar nicht erst an.
 */
async function restartRuntime() {
    if (attemptStatus.value !== 'started') {
        return;
    }

    destroying.value = true;
    stopPolling();

    try {
        await deleteJson(destroyRuntime.url({ lab: props.lab.slug }));
        router.post(startLab.url({ lab: props.lab.slug }));
    } catch {
        // Betreiber-Korrektur (Haerten): vorher lief ein Fehlschlag hier
        // als unbehandelte Rejection durch -- der nachfolgende
        // router.post()-Aufruf feuerte dann nie, der Button re-aktivierte
        // sich aber wortlos wieder, ohne dass der Lernende erfuhr, warum
        // "Neustart" nichts bewirkt hat.
        runtimeStatus.value = 'error';
        runtimeErrorMessage.value = 'sandbox_unavailable';
    } finally {
        destroying.value = false;
    }
}

onMounted(() => {
    if (runtimeStatus.value === 'queued') {
        pollTimer = setInterval(pollRuntimeState, 3000);
    }
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <Head :title="lab.title" />

    <div class="mx-auto max-w-4xl px-6 pt-10 pb-16">
        <h1 class="mb-1 text-2xl font-semibold">{{ props.lab.title }}</h1>
        <p v-if="props.lab.scenario_title" class="text-muted-foreground mb-4">
            {{ props.lab.scenario_title }}
        </p>

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <Badge variant="outline">{{ props.lab.difficulty }}</Badge>
            <Badge variant="outline"
                >{{ props.lab.points }} {{ trans('Pkt.') }}</Badge
            >
            <Badge variant="outline"
                >{{ props.lab.estimated_minutes }} {{ trans('Min.') }}</Badge
            >
            <Badge v-if="attemptStatus">{{
                statusLabels[attemptStatus] ?? attemptStatus
            }}</Badge>
        </div>

        <div v-if="briefing_html" class="lesson-prose" v-html="briefing_html" />

        <p v-else class="text-muted-foreground">
            {{ trans('Noch keine Anleitung hinterlegt.') }}
        </p>

        <Alert v-if="runtimeErrorMessage" variant="destructive" class="mt-6">
            <AlertTitle>{{
                trans('Runtime konnte nicht gestartet werden')
            }}</AlertTitle>
            <AlertDescription>
                {{
                    runtimeErrorLabels[runtimeErrorMessage] ??
                    genericRuntimeErrorLabel
                }}
            </AlertDescription>
        </Alert>

        <p
            v-if="attemptStatus === 'solved'"
            class="mt-6 text-sm font-medium text-green-600 dark:text-green-400"
        >
            {{ trans('Gelöst — gut gemacht!') }}
        </p>

        <!-- Kein Attempt: einziger Einstieg ist "Lab starten". -->
        <Form
            v-if="!attemptStatus && can_start"
            v-bind="startLab.form(lab.slug)"
            v-slot="{ processing }"
            class="mt-6"
        >
            <Button type="submit" :disabled="processing">
                {{ startButtonLabel }}
            </Button>
        </Form>

        <!-- Attempt started, aber (noch) keine/keine mehr erreichbare
             Runtime -- derselbe Start-Endpunkt ist von Natur aus sicher
             wiederholbar. Fuer einen geloesten Attempt ohne Runtime gibt
             es dagegen absichtlich KEINEN Button mehr. -->
        <div
            v-else-if="
                attemptStatus === 'started' &&
                (runtimeStatus === 'idle' || runtimeStatus === 'error')
            "
            class="mt-6"
        >
            <Form v-bind="startLab.form(lab.slug)" v-slot="{ processing }">
                <Button type="submit" :disabled="processing">
                    {{ startButtonLabel }}
                </Button>
            </Form>
        </div>

        <div
            v-else-if="runtimeStatus === 'queued'"
            class="text-muted-foreground mt-6 flex items-center gap-2 text-sm"
        >
            <Loader2 class="size-4 animate-spin" />
            {{
                trans('In der Warteschlange, Platz :position', {
                    position: queuePosition ?? '…',
                })
            }}
        </div>

        <div v-else-if="runtimeStatus === 'running'" class="mt-6 space-y-4">
            <div v-if="assertions.length > 0" class="space-y-1.5">
                <h2 class="text-sm font-medium">
                    {{ trans('Erfolgskriterien') }}
                </h2>
                <ul class="space-y-1 text-sm">
                    <li
                        v-for="assertion in assertions"
                        :key="assertion.index"
                        class="flex items-center gap-2"
                    >
                        <span
                            :class="
                                assertion.passed
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ assertion.passed ? '✓' : '○' }}
                        </span>
                        <span
                            :class="{
                                'text-muted-foreground': !assertion.passed,
                            }"
                        >
                            {{ assertionLabel(assertion) }}
                        </span>
                    </li>
                </ul>
            </div>

            <EngineTerminal :on-command="runCommand" />

            <!-- Betreiber-Entscheidung: Runtime nach Solve weiter nutzbar,
                 aber nicht neu startbar -- der Button existiert deshalb
                 NUR fuer status==='started'. -->
            <Button
                v-if="attemptStatus === 'started'"
                type="button"
                variant="ghost"
                size="sm"
                :disabled="destroying"
                @click="restartRuntime"
            >
                <Square class="size-4" />
                {{ trans('Runtime neu starten') }}
            </Button>
        </div>
    </div>
</template>
