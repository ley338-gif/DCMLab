<script setup lang="ts">
import { FlaskConical, Loader2, Square } from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import { Button } from '@/components/ui/button';
import { deleteJson, postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { sandbox as createSandboxRoute } from '@/routes/lessons';
import {
    destroy as destroySandbox,
    exec as execSandbox,
    state as sandboxState,
} from '@/routes/sandbox';

const props = defineProps<{ lessonId: string }>();

type Status = 'idle' | 'starting' | 'queued' | 'running' | 'error';

const status = ref<Status>('idle');
const sandboxId = ref<string | null>(null);
const queuePosition = ref<number | null>(null);
const errorMessage = ref<string | null>(null);
let pollTimer: ReturnType<typeof setInterval> | null = null;

function stopPolling() {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function start() {
    status.value = 'starting';
    errorMessage.value = null;

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
        status.value = 'error';
        errorMessage.value = trans(
            'Tageskontingent für die Spielwiese aufgebraucht. Versuch es morgen wieder.',
        );
        return;
    }

    if (!response.ok) {
        status.value = 'error';
        errorMessage.value = trans(
            'Die Spielwiese ist gerade nicht erreichbar. Versuch es gleich noch einmal.',
        );
        return;
    }

    const result = (await response.json()) as {
        status: string;
        sandbox_id: string;
        queue_position: number | null;
    };
    sandboxId.value = result.sandbox_id;

    if (result.status === 'running') {
        status.value = 'running';
    } else {
        status.value = 'queued';
        queuePosition.value = result.queue_position;
        pollTimer = setInterval(pollState, 3000);
    }
}

async function pollState() {
    if (!sandboxId.value) return;

    const response = await fetch(sandboxState.url(sandboxId.value), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    const result = (await response.json()) as {
        status: string;
        queue_position: number | null;
    };

    if (result.status === 'running') {
        stopPolling();
        status.value = 'running';
    } else {
        queuePosition.value = result.queue_position;
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
    status.value = 'idle';
}

onBeforeUnmount(() => {
    stopPolling();
});
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
            <EngineTerminal :on-command="runCommand" />
            <Button type="button" variant="ghost" size="sm" @click="stop">
                <Square class="size-4" />
                {{ trans('Spielwiese beenden') }}
            </Button>
        </div>
    </div>
</template>
