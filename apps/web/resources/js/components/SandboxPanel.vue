<script setup lang="ts">
import { FlaskConical, Loader2, Square } from '@lucide/vue';
import { nextTick, ref, watch } from 'vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import { Button } from '@/components/ui/button';
import { useRuntimeSession } from '@/composables/useRuntimeSession';
import { showAchievementUnlockToasts } from '@/lib/achievementToast';
import { deleteJson, postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { sandbox as createSandboxRoute } from '@/routes/lessons';
import {
    destroy as destroySandbox,
    exec as execSandbox,
    state as sandboxState,
} from '@/routes/sandbox';
import type { Achievement } from '@/types/achievement';

const props = defineProps<{ lessonId: string }>();

const sandboxId = ref<string | null>(null);

/**
 * `sandboxId` ist bei jedem Poll bereits gesetzt (siehe `start()`, wird VOR
 * `enterQueued()`/`enterRunning()` zugewiesen) -- kein Guard fuer `null`
 * noetig, anders als beim `!sandboxId.value`-Fruehausstieg der Vorversion.
 */
async function fetchRuntimeState() {
    const response = await fetch(sandboxState.url(sandboxId.value as string), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('sandbox state request failed');
    }

    return response.json();
}

const {
    status,
    queuePosition,
    errorMessage,
    enterStarting,
    enterQueued,
    enterRunning,
    enterError,
    enterIdle,
    stopPolling,
} = useRuntimeSession(fetchRuntimeState);

// PR #148, Prioritaet 4: dieselbe Live-Region/Fokus-Behandlung wie
// Labs/Show.vue -- beide teilen denselben Composable/dieselbe
// Zustandsmaschine, eine Spielwiese verdient keine schlechtere
// Accessibility als ein Lab.
const announcement = ref('');

watch(status, (value, previous) => {
    if (value === 'queued') {
        announcement.value = trans('In der Warteschlange, Platz :position', {
            position: queuePosition.value ?? '…',
        });
    } else if (value === 'running' && previous !== 'running') {
        announcement.value = trans('Runtime bereit.');
    }
});

watch(queuePosition, (position) => {
    if (status.value === 'queued') {
        announcement.value = trans('In der Warteschlange, Platz :position', {
            position: position ?? '…',
        });
    }
});

watch(errorMessage, (message) => {
    if (message !== null) {
        announcement.value = message;
    }
});

const terminal = ref<InstanceType<typeof EngineTerminal> | null>(null);

watch(status, async (value, previous) => {
    if (value === 'running' && previous !== 'running') {
        await nextTick();
        terminal.value?.focus?.();
    }
});

async function start() {
    enterStarting();

    const response = await fetch(createSandboxRoute.url(props.lessonId), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN':
                document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
        },
    });

    if (response.status === 429) {
        enterError(
            trans(
                'Tageskontingent für die Spielwiese aufgebraucht. Versuch es morgen wieder.',
            ),
        );
        return;
    }

    if (!response.ok) {
        enterError(
            trans(
                'Die Spielwiese ist gerade nicht erreichbar. Versuch es gleich noch einmal.',
            ),
        );
        return;
    }

    const result = (await response.json()) as {
        status: string;
        sandbox_id: string;
        queue_position: number | null;
        unlocked_achievements: Achievement[];
    };
    sandboxId.value = result.sandbox_id;
    showAchievementUnlockToasts(result.unlocked_achievements);

    if (result.status === 'running') {
        enterRunning();
    } else {
        enterQueued(result.queue_position);
    }
}

async function runCommand(command: string) {
    if (!sandboxId.value)
        return {
            stdout: '',
            stderr: trans('Keine Spielwiese aktiv.'),
            exit_code: 1,
        };

    return postJson<{ stdout: string; stderr: string; exit_code: number }>(
        execSandbox.url(sandboxId.value),
        {
            command,
        },
    );
}

async function stop() {
    if (!sandboxId.value) return;

    stopPolling();
    await deleteJson(destroySandbox.url(sandboxId.value)).catch(
        () => undefined,
    );
    sandboxId.value = null;
    enterIdle();
}
</script>

<template>
    <div>
        <Button
            v-if="status === 'idle' || status === 'error'"
            type="button"
            variant="outline"
            class="w-full"
            @click="start"
        >
            <FlaskConical class="size-4" />
            {{ trans('Spielwiese starten') }}
        </Button>
        <p v-if="status === 'error'" class="text-destructive mt-2 text-sm">
            {{ errorMessage }}
        </p>

        <div
            v-if="status === 'starting'"
            class="text-muted-foreground flex items-center gap-2 text-sm"
        >
            <Loader2 class="size-4 animate-spin" />
            {{ trans('Wird gestartet…') }}
        </div>

        <div
            v-if="status === 'queued'"
            class="text-muted-foreground flex items-center gap-2 text-sm"
        >
            <Loader2 class="size-4 animate-spin" />
            {{
                trans('In der Warteschlange, Platz :position', {
                    position: queuePosition ?? '…',
                })
            }}
        </div>

        <div v-if="status === 'running'" class="space-y-2">
            <EngineTerminal ref="terminal" :on-command="runCommand" />
            <Button type="button" variant="ghost" size="sm" @click="stop">
                <Square class="size-4" />
                {{ trans('Spielwiese beenden') }}
            </Button>
        </div>

        <span class="sr-only" role="status" aria-live="polite">
            {{ announcement }}
        </span>
    </div>
</template>
