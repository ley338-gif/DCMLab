<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import EngineTerminal from '@/components/EngineTerminal.vue';
import PreviousNextNavigation, {
    type NavNeighbor,
} from '@/components/PreviousNextNavigation.vue';
import ScenarioPlayer, {
    type ScenarioState,
} from '@/components/ScenarioPlayer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { showAchievementUnlockToasts } from '@/lib/achievementToast';
import { postJson } from '@/lib/api';
import { categoryLabels } from '@/lib/nodeCatalog';
import { trans } from '@/lib/trans';
import {
    action as triggerActionRoute,
    config as setConfigRoute,
    exec as execRoute,
    flag as flagRoute,
    hint as hintRoute,
    index as nodesIndex,
    show as showNode,
    state as stateRoute,
    writeUp as writeUpRoute,
} from '@/routes/nodes';
import type { Achievement } from '@/types/achievement';

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
    hosts?: Record<string, Host>;
    scenario?: ScenarioState;
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

type NodeNeighbor = { slug: string; title: string } | null;

const props = defineProps<{
    node: {
        slug: string;
        title: string;
        scenario_title: string;
        difficulty: string;
        points: number;
        category: string;
        interaction: string;
        estimated_minutes: number;
    };
    prev: NodeNeighbor;
    next: NodeNeighbor;
    briefing_html: string;
    hints: Hint[];
    write_up_html: string | null;
    templates: { label_key: string; command: string }[];
    placeholders: string[];
    state: EngineState;
    attempt: { status: string };
    // CMS-7d.3 Phase 6 (ADR 0118): gesetzt fuer die Draft-Vorschau eines
    // Autors (`StudioNodeController::preview()`) -- es gibt dabei keinen
    // echten `NodeAttempt`/keine echte Engine-Session (Betreiber-Vorgabe),
    // deshalb wird der interaktive Sandbox-/Flag-Teil durch einen
    // Platzhalter ersetzt. Briefing/Hinweise/Write-up sind in diesem Fall
    // bereits vollstaendig aufgedeckt (siehe `LearnerViewBuilder::
    // nodePreviewProps()`), brauchen also keine eigene Sonderbehandlung.
    preview?: boolean;
}>();

function toNavNeighbor(neighbor: NodeNeighbor, label: string): NavNeighbor {
    return neighbor
        ? { href: showNode(neighbor.slug).url, label, title: neighbor.title }
        : null;
}

const prevNav = computed(() =>
    toNavNeighbor(props.prev, trans('Vorherige Herausforderung')),
);
const nextNav = computed(() =>
    toNavNeighbor(props.next, trans('Nächste Herausforderung')),
);

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
    const entry = Object.entries(state.value.hosts ?? {}).find(([, h]) =>
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

async function chooseOption(optionId: string) {
    const result = await postJson<{ state: EngineState }>(
        triggerActionRoute.url(props.node.slug),
        { host: 'player', action: optionId },
    );
    state.value = result.state;
}

async function restartScenario() {
    await postJson(triggerActionRoute.url(props.node.slug), {
        host: 'player',
        action: 'restart',
    });
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
    const result = await postJson<{
        correct: boolean;
        points?: number;
        unlocked_achievements: Achievement[];
    }>(flagRoute.url(props.node.slug), {
        value: flagValue.value,
    });
    flagFeedback.value = result.correct ? 'correct' : 'wrong';
    if (result.correct) {
        await fetchState();
        // Nach dem Loesen wird das Write-up automatisch gezeigt, ohne
        // Punktabzug (Engine straft das nur "vorab" ab, Abschnitt 5.3).
        await viewWriteUp();
        showAchievementUnlockToasts(result.unlocked_achievements);
    }
}
</script>

<template>
    <Head :title="node.title" />

    <div class="mx-auto max-w-6xl px-6 pt-10">
        <Breadcrumbs
            class="mb-3"
            :breadcrumbs="[
                { title: trans('Herausforderungen'), href: nodesIndex() },
                {
                    title: categoryLabels[node.category] ?? node.category,
                    href: nodesIndex(),
                },
                { title: node.title, href: showNode(node.slug) },
            ]"
        />

        <nav
            v-if="prev || next"
            class="text-muted-foreground mb-6 flex items-center justify-between text-sm"
            :aria-label="trans('Herausforderungs-Navigation')"
        >
            <Link
                v-if="prev"
                :href="showNode(prev.slug)"
                class="hover:text-foreground inline-flex items-center gap-1"
            >
                <ArrowLeft class="size-3.5" aria-hidden="true" />
                {{ trans('Vorherige Herausforderung') }}
            </Link>
            <span v-else />

            <Link
                v-if="next"
                :href="showNode(next.slug)"
                class="hover:text-foreground inline-flex items-center gap-1"
            >
                {{ trans('Nächste Herausforderung') }}
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </Link>
        </nav>
    </div>

    <div>
        <main
            class="mx-auto grid max-w-6xl gap-6 px-6 pb-10 lg:grid-cols-[1fr_22rem]"
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

                <Card v-if="preview">
                    <CardContent class="text-muted-foreground text-sm">
                        {{
                            trans(
                                'Vorschau: Sandbox und Terminal sind hier nicht verfügbar.',
                            )
                        }}
                    </CardContent>
                </Card>

                <ScenarioPlayer
                    v-else-if="node.interaction === 'scenario'"
                    :scenario="state.scenario!"
                    @choose="chooseOption"
                    @restart="restartScenario"
                />

                <Tabs v-else default-value="workstation">
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

                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm">{{
                            trans('Lösung & Write-up')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent
                        v-if="writeUpHtml"
                        class="node-prose text-sm"
                        v-html="writeUpHtml"
                    />
                    <CardContent v-else class="space-y-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="viewWriteUp"
                        >
                            {{ trans('Write-up ansehen') }}
                        </Button>
                        <p class="text-muted-foreground text-xs">
                            {{
                                trans(
                                    'Hinweis: Das Öffnen des Write-ups setzt die erreichbaren Punkte auf 0.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <aside class="space-y-4">
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

                <Card v-if="!preview">
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
            </aside>
        </main>

        <div v-if="prevNav || nextNav" class="mx-auto max-w-6xl px-6 pb-10">
            <PreviousNextNavigation :prev="prevNav" :next="nextNav" />
        </div>
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
.node-prose table {
    width: 100%;
    margin-bottom: 0.75rem;
    border-collapse: collapse;
    font-size: 0.8125rem;
}
.node-prose th,
.node-prose td {
    border: 1px solid var(--border);
    padding: 0.375rem 0.625rem;
    text-align: left;
    vertical-align: top;
}
</style>
