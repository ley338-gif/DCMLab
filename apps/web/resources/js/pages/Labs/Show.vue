<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, Loader2, Square } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    type RuntimeStatus,
    useRuntimeSession,
} from '@/composables/useRuntimeSession';
import { showAchievementUnlockToasts } from '@/lib/achievementToast';
import { deleteJson, postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import {
    exec as execLab,
    index as labsIndex,
    start as startLab,
} from '@/routes/labs';
import {
    destroy as destroyRuntime,
    state as runtimeState,
} from '@/routes/labs/runtime';
import type { Achievement } from '@/types/achievement';

type AttemptStatus = 'started' | 'solved' | 'abandoned';

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
        status: 'queued' | 'running' | 'sandbox_unavailable' | 'expired';
        queue_position: number | null;
    } | null;
    assertions: AssertionState[];
    // Betreiber-Korrektur: lokales Prop statt globalem Flash-Sharing --
    // start()s einziger Rückkanal ist der Redirect zurück auf show().
    runtime_error: string | null;
    // PR #148, Prioritaet 1: immer berechnet (siehe LabController::show()),
    // aber nur im Abschluss-Bereich eines geloesten Attempts gezeigt.
    next_step: {
        type: 'lesson' | 'labs_index';
        lesson_id: string | null;
        lesson_title: string | null;
    };
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
    // PR #148, "Runtime ended/expired": vom Idle-Timeout-Cleanup beendet
    // (CMS-8b), kein Fehler des Lernenden -- eigener Text statt der
    // generischen "nicht erreichbar"-Meldung.
    expired: trans('Deine Sitzung wurde wegen Inaktivität beendet.'),
};

// Betreiber-Vorgabe: ein unbekannter Fehlerschlüssel bekommt nur eine
// generische Meldung -- nie den rohen Schlüssel selbst anzeigen.
const genericRuntimeErrorLabel = trans(
    'Die Runtime konnte nicht gestartet werden. Bitte später erneut versuchen.',
);

// Betreiber-Vorgabe (Status-Terminologie): dieselben drei lernenden-
// facing Begriffe wie Labs/Index.vue ("Nicht gestartet" braucht hier keinen
// Badge -- fehlt attemptStatus, wird gar kein Badge gezeigt). Die
// internen Enum-Werte (`started`/`solved`/`abandoned`) bleiben unveraendert,
// nur das angezeigte Label wechselt von "Begonnen" zu "In Bearbeitung".
const statusLabels: Record<AttemptStatus, string> = {
    started: trans('In Bearbeitung'),
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
const assertions = ref<AssertionState[]>(props.assertions);
const destroying = ref(false);

const initialRuntimeStatus: RuntimeStatus =
    props.runtime === null
        ? 'idle'
        : props.runtime.status === 'sandbox_unavailable' ||
            props.runtime.status === 'expired'
          ? 'error'
          : props.runtime.status;

async function fetchRuntimeState() {
    const response = await fetch(runtimeState.url({ lab: props.lab.slug }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('lab runtime state request failed');
    }

    return response.json();
}

const {
    status: runtimeStatus,
    queuePosition,
    errorMessage: runtimeErrorMessage,
    enterError,
    stopPolling,
} = useRuntimeSession(
    fetchRuntimeState,
    initialRuntimeStatus,
    props.runtime?.queue_position ?? null,
);

// Betreiber-Korrektur (Haerten): einzige Quelle fuer die sichtbare
// Fehlermeldung -- start()s Session-Flash (`props.runtime_error`) ODER,
// falls das initiale `show()` bereits eine nicht erreichbare Runtime
// gemeldet hat, dieselbe Meldung von Anfang an. Jede spaetere, vom Client
// selbst entdeckte Nichtverfuegbarkeit (Polling, exec(), restartRuntime())
// schreibt ueber `enterError()` hierher, statt den Lernenden mit einem
// erklaerungslosen Retry-Button allein zu lassen.
runtimeErrorMessage.value =
    props.runtime_error ??
    (props.runtime?.status === 'sandbox_unavailable' ||
    props.runtime?.status === 'expired'
        ? props.runtime.status
        : null);

const startButtonLabel = computed(() => {
    if (!attemptStatus.value) {
        return trans('Lab starten');
    }

    if (runtimeErrorMessage.value === 'expired') {
        return trans('Neue Sitzung starten');
    }

    return runtimeErrorMessage.value
        ? trans('Erneut versuchen')
        : trans('Runtime starten');
});

const runtimeErrorLabel = computed(() =>
    runtimeErrorMessage.value === null
        ? null
        : (runtimeErrorLabels[runtimeErrorMessage.value] ??
          genericRuntimeErrorLabel),
);

// PR #148, "Runtime ended/expired": eine beendete Sitzung ist kein
// fehlgeschlagener Start -- eigener Alert-Titel statt "Runtime konnte
// nicht gestartet werden", der bei einer vorher erfolgreich gelaufenen
// Sitzung fachlich falsch waere.
const runtimeErrorTitle = computed(() =>
    runtimeErrorMessage.value === 'expired'
        ? trans('Sitzung beendet')
        : trans('Runtime konnte nicht gestartet werden'),
);

// PR #148, Prioritaet 1: der Rueckweg im Abschluss-Bereich -- Fallback-
// Kette und Daten kommen vollstaendig aus `next_step` (siehe
// `DashboardHomeService::nextStepAfterLab()`), hier nur noch Href/Label.
const nextStepHref = computed(() =>
    props.next_step.type === 'lesson' && props.next_step.lesson_id !== null
        ? showLesson(props.next_step.lesson_id)
        : labsIndex(),
);

const nextStepLabel = computed(() =>
    props.next_step.type === 'lesson'
        ? trans('Zurück zur Lektion :lesson', {
              lesson:
                  props.next_step.lesson_title ??
                  props.next_step.lesson_id ??
                  '',
          })
        : trans('Alle Labs'),
);

/**
 * PR #148, Prioritaet 4: Runtime-Zustandsaenderungen sind rein visuell
 * (Icon/Text/Terminal erscheint) und damit fuer Screenreader-Nutzer
 * stumm -- eine einzelne `aria-live="polite"`-Region traegt jede
 * Statusaenderung nach, ohne bei jeder einzelnen Aenderung `assertive` zu
 * werden. Bewusst zurueckhaltend: der Warteschlangenplatz wird nur
 * angesagt, wenn die Runtime tatsaechlich noch wartet, nicht bei jedem
 * einzelnen Poll-Tick mit unveraendertem Wert (Vue's `watch()` feuert bei
 * einem gleichen Ref-Wert ohnehin nicht erneut).
 */
const announcement = ref('');

watch(runtimeStatus, (status, previous) => {
    if (status === 'queued') {
        announcement.value = trans('In der Warteschlange, Platz :position', {
            position: queuePosition.value ?? '…',
        });
    } else if (status === 'running' && previous !== 'running') {
        announcement.value = trans('Runtime bereit.');
    }
});

watch(queuePosition, (position) => {
    if (runtimeStatus.value === 'queued') {
        announcement.value = trans('In der Warteschlange, Platz :position', {
            position: position ?? '…',
        });
    }
});

watch(runtimeErrorLabel, (label) => {
    if (label !== null) {
        announcement.value = label;
    }
});

const terminal = ref<InstanceType<typeof EngineTerminal> | null>(null);

// Sobald die Runtime von Warteschlange/Start auf "bereit" wechselt, den
// Fokus aktiv in den Arbeitsbereich legen -- beim ERSTEN Laden mit bereits
// laufender Runtime (Reload waehrend `running`) bewusst NICHT, das waere ein
// ueberraschender Fokusklau direkt beim Seitenaufruf. `watch()` ohne
// `immediate` feuert von sich aus nur bei einer tatsaechlichen Aenderung.
watch(runtimeStatus, async (status, previous) => {
    if (status === 'running' && previous !== 'running') {
        await nextTick();
        terminal.value?.focus?.();
    }
});

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

        const previouslyPassed = new Set(
            assertions.value.filter((a) => a.passed).map((a) => a.index),
        );
        const newlyPassed = result.assertions.filter(
            (a) => a.passed && !previouslyPassed.has(a.index),
        );
        assertions.value = result.assertions;

        if (newlyPassed.length > 0) {
            announcement.value = newlyPassed
                .map((a) =>
                    trans('Erfolgskriterium :n erfüllt.', { n: a.index + 1 }),
                )
                .join(' ');
        }

        if (result.all_satisfied) {
            attemptStatus.value = 'solved';
            announcement.value = trans(
                'Lab abgeschlossen. :points Punkte erhalten.',
                { points: props.lab.points },
            );
        }

        showAchievementUnlockToasts(result.unlocked_achievements);

        return {
            stdout: result.stdout,
            stderr: result.stderr,
            exit_code: result.exit_code,
        };
    } catch {
        enterError('sandbox_unavailable');

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
        enterError('sandbox_unavailable');
    } finally {
        destroying.value = false;
    }
}
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

        <Alert v-if="runtimeErrorLabel" variant="destructive" class="mt-6">
            <AlertTitle>{{ runtimeErrorTitle }}</AlertTitle>
            <AlertDescription>
                {{ runtimeErrorLabel }}
            </AlertDescription>
        </Alert>

        <span class="sr-only" role="status" aria-live="polite">
            {{ announcement }}
        </span>

        <!-- Abschluss-Bereich (PR #148, Prioritaet 5): bewusst kein
             Gamification-Feuerwerk -- nur Bestaetigung, Punkte und ein
             konkreter naechster Schritt statt Browser-Back als einzigem
             Rueckweg. Achievement-Toasts laufen unabhaengig davon weiter
             (siehe runCommand()). -->
        <div
            v-if="attemptStatus === 'solved'"
            class="bg-muted/40 mt-6 rounded-lg border p-4"
        >
            <p
                class="flex items-center gap-2 text-sm font-medium text-green-600 dark:text-green-400"
            >
                <CheckCircle2 class="size-4" aria-hidden="true" />
                {{ trans('Lab abgeschlossen') }}
            </p>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ trans(':points Punkte erhalten', { points: lab.points }) }}
            </p>
            <Link
                :href="nextStepHref"
                class="text-primary mt-3 inline-flex items-center gap-1 text-sm font-medium hover:underline"
            >
                {{ nextStepLabel }}
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>

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

            <EngineTerminal ref="terminal" :on-command="runCommand" />

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
