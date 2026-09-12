<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import {
    action as triggerActionRoute,
    config as setConfigRoute,
    exec as execRoute,
    flag as flagRoute,
    hint as hintRoute,
    state as stateRoute,
    writeUp as writeUpRoute,
} from '@/routes/nodes';

type HostService = {
    port: number;
    type: string;
    accepts: string[];
    ae_title?: string;
};
type Host = {
    ip: string;
    role?: string;
    services?: HostService[];
    counters?: { accepted: number; rejected: number };
    bestand?: { studies: number; series: number; instances: number };
    config_editable?: boolean;
    config?: Record<string, string>;
};

type EngineState = {
    node_slug: string;
    hosts: Record<string, Host>;
    hints_used: string[];
    write_up_seen: boolean;
    solved: boolean;
    points: number;
    stuck: boolean;
};

type Hint = {
    id: string;
    cost: number;
    used: boolean;
    text_html: string | null;
};

const props = defineProps<{
    node: {
        slug: string;
        title: string;
        scenario_title: string;
        difficulty: string;
        points: number;
        estimated_minutes: number;
    };
    briefing_html: string;
    hints: Hint[];
    write_up_html: string | null;
    templates: { label_key: string; command: string }[];
    placeholders: string[];
    state: EngineState;
    attempt: { status: string };
}>();

const state = ref<EngineState>(props.state);
const hints = reactive<Hint[]>(props.hints.map((h) => ({ ...h })));
const writeUpHtml = ref(props.write_up_html);
const flagValue = ref('');
const flagFeedback = ref<'correct' | 'wrong' | null>(null);
const terminalRef = ref<InstanceType<typeof EngineTerminal> | null>(null);
const actionLog = ref<string[]>([]);
const configDrafts = reactive<Record<string, string>>({});

function findHost(
    predicate: (host: Host) => boolean,
): { name: string; host: Host } | null {
    const entry = Object.entries(state.value.hosts).find(([, h]) =>
        predicate(h),
    );

    return entry ? { name: entry[0], host: entry[1] } : null;
}

const shellHost = computed(() => findHost((h) => h.role === 'shell'));
const consoleHost = computed(() => findHost((h) => Boolean(h.config_editable)));
const archiveHost = computed(() => findHost((h) => Boolean(h.services)));

// state ist ein GET-Endpunkt -- postJson passt hier nicht, ein eigener
// schlanker GET-Aufruf reicht.
async function fetchState() {
    const response = await fetch(stateRoute.url(props.node.slug), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    state.value = (await response.json()) as EngineState;
}

async function runCommand(command: string) {
    if (!shellHost.value)
        return {
            stdout: '',
            stderr: trans('Kein Host mit Shell gefunden.'),
            exit_code: 1,
        };

    const result = await postJson<{
        stdout: string;
        stderr: string;
        exit_code: number;
    }>(execRoute.url(props.node.slug), {
        host: shellHost.value.name,
        command,
    });
    await fetchState();

    return result;
}

function insertTemplate(command: string) {
    terminalRef.value?.insertTemplate(command);
}

async function saveConfig(host: string, field: string) {
    const value = configDrafts[field] ?? '';
    await postJson(setConfigRoute.url(props.node.slug), { host, field, value });
    await fetchState();
}

async function sendStudy(host: string) {
    const result = await postJson<{ log: string[]; error?: string }>(
        triggerActionRoute.url(props.node.slug),
        {
            host,
            action: 'send_study',
        },
    );
    actionLog.value = result.log ?? [];
    await fetchState();
}

async function useHint(hintId: string) {
    const result = await postJson<{
        error?: string;
        points: number;
        text_html: string | null;
    }>(hintRoute.url(props.node.slug), {
        hint_id: hintId,
    });
    if (result.error) return;

    const hint = hints.find((h) => h.id === hintId);
    if (hint) {
        hint.used = true;
        hint.text_html = result.text_html;
    }
    await fetchState();
}

async function viewWriteUp() {
    const result = await postJson<{ write_up_html: string }>(
        writeUpRoute.url(props.node.slug),
    );
    writeUpHtml.value = result.write_up_html;
    await fetchState();
}

async function submitFlag() {
    const result = await postJson<{ correct: boolean; points?: number }>(
        flagRoute.url(props.node.slug),
        {
            value: flagValue.value,
        },
    );
    flagFeedback.value = result.correct ? 'correct' : 'wrong';
    if (result.correct) {
        await fetchState();
        // Nach dem Loesen wird das Write-up automatisch gezeigt, ohne
        // Punktabzug (Engine straft das nur "vorab" ab, Abschnitt 5.3).
        await viewWriteUp();
    }
}
</script>

<template>
    <Head :title="node.title" />

    <div class="bg-background min-h-screen">
        <main
            class="mx-auto grid max-w-6xl gap-6 px-6 py-10 lg:grid-cols-[1fr_22rem]"
        >
            <div class="space-y-6">
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <Badge variant="outline">{{ node.difficulty }}</Badge>
                        <Badge variant="outline">{{
                            trans(':points Punkte', { points: node.points })
                        }}</Badge>
                        <Badge
                            v-if="state.solved"
                            class="bg-green-600 text-white"
                            >{{ trans('Gelöst') }}</Badge
                        >
                        <Badge
                            v-if="state.stuck && !state.solved"
                            variant="destructive"
                            >{{
                                trans(
                                    'Stecken geblieben? Sieh dir Hinweis 1 an.',
                                )
                            }}</Badge
                        >
                    </div>
                    <h1 class="text-2xl font-semibold">
                        {{ node.scenario_title }}
                    </h1>
                </div>

                <Tabs default-value="workstation">
                    <TabsList>
                        <TabsTrigger v-if="shellHost" value="workstation">{{
                            trans('Workstation')
                        }}</TabsTrigger>
                        <TabsTrigger v-if="consoleHost" value="console">{{
                            trans('Modalität / Konsole')
                        }}</TabsTrigger>
                        <TabsTrigger v-if="archiveHost" value="archive">{{
                            trans('Archiv')
                        }}</TabsTrigger>
                    </TabsList>

                    <TabsContent
                        v-if="shellHost"
                        value="workstation"
                        class="space-y-3"
                    >
                        <EngineTerminal
                            ref="terminalRef"
                            :on-command="runCommand"
                            :placeholders="placeholders"
                        />
                        <div
                            v-if="templates.length"
                            class="flex flex-wrap gap-2"
                        >
                            <Button
                                v-for="template in templates"
                                :key="template.label_key"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="font-mono text-xs"
                                @click="insertTemplate(template.command)"
                            >
                                {{ template.command }}
                            </Button>
                        </div>
                    </TabsContent>

                    <TabsContent
                        v-if="consoleHost"
                        value="console"
                        class="space-y-4"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm">{{
                                    trans('Netzwerkkonfiguration')
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="(value, field) in consoleHost.host
                                        .config"
                                    :key="field"
                                    class="flex items-center gap-2"
                                >
                                    <label
                                        class="text-muted-foreground w-32 text-xs uppercase"
                                        >{{ field }}</label
                                    >
                                    <Input
                                        :model-value="
                                            configDrafts[field] ?? value
                                        "
                                        class="font-mono text-sm"
                                        @update:model-value="
                                            (v) =>
                                                (configDrafts[field] =
                                                    String(v))
                                        "
                                        @blur="
                                            saveConfig(consoleHost!.name, field)
                                        "
                                    />
                                </div>
                                <Button
                                    type="button"
                                    @click="sendStudy(consoleHost.name)"
                                >
                                    {{ trans('Auftrag auslösen') }}
                                </Button>
                            </CardContent>
                        </Card>
                        <Card v-if="actionLog.length">
                            <CardHeader>
                                <CardTitle class="text-sm">{{
                                    trans('Auftragsprotokoll')
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre class="text-xs whitespace-pre-wrap">{{
                                    actionLog.join('\n')
                                }}</pre>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent v-if="archiveHost" value="archive">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-sm">{{
                                    trans('Statusseite')
                                }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm">
                                <div
                                    v-for="service in archiveHost.host.services"
                                    :key="service.port"
                                >
                                    {{
                                        trans('Port :port · :type', {
                                            port: service.port,
                                            type: service.type,
                                        })
                                    }}
                                </div>
                                <div v-if="archiveHost.host.counters">
                                    {{
                                        trans(
                                            'Angenommen: :accepted · Abgelehnt: :rejected',
                                            {
                                                accepted:
                                                    archiveHost.host.counters
                                                        .accepted,
                                                rejected:
                                                    archiveHost.host.counters
                                                        .rejected,
                                            },
                                        )
                                    }}
                                </div>
                                <div v-if="archiveHost.host.bestand">
                                    {{
                                        trans(
                                            'Bestand: :studies Studies · :series Serien · :instances Instanzen',
                                            {
                                                studies:
                                                    archiveHost.host.bestand
                                                        .studies,
                                                series: archiveHost.host.bestand
                                                    .series,
                                                instances:
                                                    archiveHost.host.bestand
                                                        .instances,
                                            },
                                        )
                                    }}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            <aside class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">{{
                            trans('Briefing')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent
                        class="node-prose text-sm"
                        v-html="briefing_html"
                    />
                </Card>

                <Card v-if="hints.length">
                    <CardHeader>
                        <CardTitle class="text-sm">{{
                            trans('Hinweise')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="hint in hints" :key="hint.id">
                            <div
                                v-if="hint.used"
                                class="node-prose text-sm"
                                v-html="hint.text_html"
                            />
                            <Button
                                v-else
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="useHint(hint.id)"
                            >
                                {{
                                    trans('Hinweis ansehen (-:cost Punkt(e))', {
                                        cost: hint.cost,
                                    })
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">{{
                            trans('Flag')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Input
                            v-model="flagValue"
                            :placeholder="trans('Flag eingeben')"
                            @keyup.enter="submitFlag"
                        />
                        <Button
                            type="button"
                            class="w-full"
                            @click="submitFlag"
                            >{{ trans('Flag prüfen') }}</Button
                        >
                        <p
                            v-if="flagFeedback === 'correct'"
                            class="text-sm text-green-600"
                        >
                            {{
                                trans('Richtig! :points Punkte.', {
                                    points: state.points,
                                })
                            }}
                        </p>
                        <p
                            v-else-if="flagFeedback === 'wrong'"
                            class="text-destructive text-sm"
                        >
                            {{ trans('Leider falsch.') }}
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="writeUpHtml">
                    <CardHeader>
                        <CardTitle class="text-sm">{{
                            trans('Write-up')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent
                        class="node-prose text-sm"
                        v-html="writeUpHtml"
                    />
                </Card>
                <Button
                    v-else
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="viewWriteUp"
                >
                    {{ trans('Write-up ansehen (setzt Punkte auf 0)') }}
                </Button>
            </aside>
        </main>
    </div>
</template>

<style>
.node-prose p {
    margin-bottom: 0.75rem;
}
.node-prose pre {
    overflow-x: auto;
    border-radius: 0.5rem;
    background: var(--muted);
    padding: 0.75rem;
    font-size: 0.8125rem;
}
</style>
