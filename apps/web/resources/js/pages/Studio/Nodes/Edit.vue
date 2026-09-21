<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { GripVertical } from '@lucide/vue';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RichContentWorkbench from '@/components/RichContent/RichContentWorkbench.vue';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import type { GlossaryTermOption } from '@/lib/richContent/slashCommand';
import type { RichContentDocument } from '@/types/richContent';
import { show as showNode } from '@/routes/nodes';
import { index as nodesIndex } from '@/routes/studio/nodes';
import { publish, submit } from '@/routes/author/quiz-versions';
import {
    archive as archiveNode,
    duplicate as duplicateNode,
    restore as restoreNode,
    update as updateNode,
    updateThemenfeld as updateThemenfeldNode,
    validate as validateNode,
} from '@/routes/studio/nodes';

type Difficulty = 'easy' | 'medium' | 'hard' | 'insane';
type Interaction = 'terminal' | 'scenario';
type Status = 'draft' | 'published' | 'archived';

type Hint = { id: string; cost: number };

/**
 * Der `node_content`-Umschlag (ADR 0115/0118) -- Briefing/jeder Hint/
 * Write-up ist ein eigenstaendiges RichContentDocument, `hints` hier ist
 * NICHT dasselbe wie `NodeFields.hints` oben (dort id/cost-Metadaten,
 * hier der Text je Hint-Id). Beide Seiten muessen dieselben Ids tragen
 * (NodeActivity::checkHintIdConsistency()) -- addHint()/removeHint()/
 * renameHintId() halten sie synchron.
 */
type NodeContentEnvelope = {
    type: 'node_content';
    version: 1;
    briefing: RichContentDocument;
    hints: Record<string, RichContentDocument>;
    write_up: RichContentDocument;
};

type NodeFields = {
    title: string;
    scenario_title: string;
    difficulty: Difficulty;
    points: number;
    category: string;
    interaction: Interaction;
    estimated_minutes: number;
    skills: string[];
    related_lessons: string[];
    hints: Hint[];
    rich_content: NodeContentEnvelope;
};

function emptyDocument(): RichContentDocument {
    return { type: 'doc', version: 1, content: [] };
}

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
} | null;

type VersionRow = {
    id: number;
    status: string;
    is_current: boolean;
    author_name: string | null;
    published_at: string | null;
};

const props = defineProps<{
    node: {
        slug: string;
        title: string;
        status: Status;
        themenfeld_id: number | null;
        has_runtime_config: boolean;
    };
    fields: NodeFields;
    themenfelder: { id: number; slug: string }[];
    skills_catalog: string[];
    glossary: GlossaryTermOption[];
    pending_version: PendingVersion;
    versions: VersionRow[];
    can_manage: boolean;
    can_publish: boolean;
    preview_url: string;
}>();

const fields = ref<NodeFields>({ ...props.fields });
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);
const themenfeldId = ref<number | null>(props.node.themenfeld_id);

/**
 * Leichtgewichtiger "ungespeichert"-Hinweis, analog Studio/Lessons/Edit.vue
 * und Studio/Labs/Edit.vue -- vergleicht den aktuellen Stand gegen den
 * zuletzt erfolgreich gespeicherten Schnappschuss, kein neuer Speicher-/
 * Autosave-Mechanismus.
 *
 * Betreiber-Befund: deckt bewusst NUR `fields` ab (den Entwurf), nicht
 * `themenfeldId` -- das Themenfeld wird ueber saveThemenfeld() sofort und
 * unabhaengig vom Entwurf gespeichert (siehe Hinweistext am Themenfeld-
 * Select). Die Anzeige heisst deshalb "Entwurf gespeichert", nicht nur
 * "Gespeichert", um diesen Geltungsbereich nicht zu verschleiern.
 */
const lastSavedSnapshot = ref(JSON.stringify(props.fields));
const hasUnsavedChanges = computed(
    () => JSON.stringify(fields.value) !== lastSavedSnapshot.value,
);

const statusLabels: Record<string, string> = {
    draft: trans('Entwurf'),
    review: trans('Zur Prüfung eingereicht'),
    published: trans('Veröffentlicht'),
};

const difficultyLabels: Record<Difficulty, string> = {
    easy: trans('Leicht'),
    medium: trans('Mittel'),
    hard: trans('Schwer'),
    insane: trans('Extrem'),
};

const relatedLessonsText = {
    get(): string {
        return fields.value.related_lessons.join(', ');
    },
    set(value: string) {
        fields.value.related_lessons = value
            .split(',')
            .map((part) => part.trim())
            .filter((part) => part !== '');
    },
};

function toggleSkill(skill: string) {
    if (fields.value.skills.includes(skill)) {
        fields.value.skills = fields.value.skills.filter((s) => s !== skill);
    } else {
        fields.value.skills = [...fields.value.skills, skill];
    }
}

const draggedHintIndex = ref<number | null>(null);

function addHint() {
    const nextNumber = fields.value.hints.length + 1;
    const id = `h${nextNumber}`;
    fields.value.hints = [...fields.value.hints, { id, cost: 1 }];
    fields.value.rich_content.hints[id] = emptyDocument();
}

function removeHint(index: number) {
    const id = fields.value.hints[index]?.id;
    fields.value.hints = fields.value.hints.filter((_, i) => i !== index);

    if (id !== undefined) {
        delete fields.value.rich_content.hints[id];
    }
}

/**
 * Haelt `rich_content.hints` synchron, wenn eine Hint-Id im Textfeld
 * umbenannt wird -- ohne das wuerde der bereits geschriebene Hint-Text
 * unter der alten Id verwaist zurueckbleiben (NodeActivity meldet das
 * zwar beim Pruefen, aber verlustfrei umbenennen ist die bessere UX).
 */
function renameHintId(index: number, newId: string) {
    const oldId = fields.value.hints[index]?.id;

    if (oldId === newId) {
        return;
    }

    const document =
        oldId !== undefined
            ? (fields.value.rich_content.hints[oldId] ?? emptyDocument())
            : emptyDocument();

    if (oldId !== undefined) {
        delete fields.value.rich_content.hints[oldId];
    }

    fields.value.rich_content.hints[newId] = document;
    fields.value.hints[index].id = newId;
}

function onHintDragStart(index: number) {
    draggedHintIndex.value = index;
}

function onHintDrop(targetIndex: number) {
    const fromIndex = draggedHintIndex.value;
    draggedHintIndex.value = null;

    if (fromIndex === null || fromIndex === targetIndex) {
        return;
    }

    const reordered = [...fields.value.hints];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(targetIndex, 0, moved);
    fields.value.hints = reordered;
}

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validateNode.url({ node: props.node.slug }),
            fields.value,
        );
        issues.value = result.issues;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    const snapshotAtSaveTime = JSON.stringify(fields.value);
    router.patch(updateNode.url({ node: props.node.slug }), fields.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        onSuccess: () => {
            lastSavedSnapshot.value = snapshotAtSaveTime;
            runValidation();
        },
    });
}

function submitForReview() {
    if (props.pending_version === null) {
        return;
    }

    acting.value = true;
    router.post(
        submit.url({ version: props.pending_version.id }),
        {},
        { preserveScroll: true, onFinish: () => (acting.value = false) },
    );
}

function publishVersion() {
    if (props.pending_version === null) {
        return;
    }

    acting.value = true;
    router.post(
        publish.url({ version: props.pending_version.id }),
        {},
        { preserveScroll: true, onFinish: () => (acting.value = false) },
    );
}

function saveThemenfeld() {
    if (themenfeldId.value === null) {
        return;
    }

    router.patch(
        updateThemenfeldNode.url({ node: props.node.slug }),
        { themenfeld_id: themenfeldId.value },
        { preserveScroll: true },
    );
}

function duplicate() {
    router.post(duplicateNode.url({ node: props.node.slug }));
}

function archive() {
    router.post(
        archiveNode.url({ node: props.node.slug }),
        {},
        { preserveScroll: true },
    );
}

function restore() {
    router.post(
        restoreNode.url({ node: props.node.slug }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="trans('Node bearbeiten: :title', { title: node.title })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Nodes'), href: nodesIndex() },
                { title: node.title, href: '' },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ node.title }}</h1>
                <Badge
                    :variant="
                        node.status === 'published' ? 'default' : 'outline'
                    "
                >
                    {{
                        node.status === 'published'
                            ? trans('Veröffentlicht')
                            : node.status === 'archived'
                              ? trans('Archiviert')
                              : trans('Entwurf')
                    }}
                </Badge>
                <Badge v-if="pending_version" variant="secondary">
                    {{ statusLabels[pending_version.status] }}
                </Badge>
                <span class="text-muted-foreground text-xs">
                    {{
                        hasUnsavedChanges
                            ? trans('Entwurf: ungespeicherte Änderungen')
                            : trans('Entwurf gespeichert')
                    }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a :href="preview_url" target="_blank" rel="noopener">
                    <Button type="button" variant="outline">{{
                        trans('Vorschau')
                    }}</Button>
                </a>
                <a
                    v-if="node.has_runtime_config"
                    :href="showNode(node.slug).url"
                    target="_blank"
                    rel="noopener"
                >
                    <Button type="button" variant="outline">{{
                        trans('Im Lernpfad testen')
                    }}</Button>
                </a>
            </div>
        </div>

        <p
            v-if="!node.has_runtime_config"
            class="text-muted-foreground mb-6 text-sm"
        >
            {{
                trans(
                    'Diese Node hat noch keine Runtime-Konfiguration (Sandbox/Engine) — die Vorschau zeigt Titel und Text, aber noch keine funktionierende Aufgabe. Das legt ein Administrator zusätzlich in content/nodes/ an.',
                )
            }}
        </p>

        <Alert v-if="issues.length > 0" variant="destructive" class="mb-6">
            <AlertTitle>{{ trans('Befunde') }}</AlertTitle>
            <AlertDescription>
                <ul class="list-inside list-disc">
                    <li v-for="(issue, index) in issues" :key="index">
                        {{ issue }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Grunddaten')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="title">{{ trans('Titel') }}</Label>
                                <Input id="title" v-model="fields.title" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="scenario_title">{{
                                    trans('Szenario-Titel')
                                }}</Label>
                                <Input
                                    id="scenario_title"
                                    v-model="fields.scenario_title"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Slug') }}</Label>
                                <Input :model-value="node.slug" disabled />
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        trans(
                                            'Fachlicher Schlüssel und URL — nicht änderbar.',
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="category">{{
                                    trans('Kategorie')
                                }}</Label>
                                <Input
                                    id="category"
                                    v-model="fields.category"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="difficulty">{{
                                    trans('Schwierigkeit')
                                }}</Label>
                                <select
                                    id="difficulty"
                                    v-model="fields.difficulty"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="(
                                            label, difficulty
                                        ) in difficultyLabels"
                                        :key="difficulty"
                                        :value="difficulty"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="points">{{
                                    trans('Punkte')
                                }}</Label>
                                <Input
                                    id="points"
                                    v-model.number="fields.points"
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="estimated_minutes">{{
                                    trans('Dauer (Minuten)')
                                }}</Label>
                                <Input
                                    id="estimated_minutes"
                                    v-model.number="fields.estimated_minutes"
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="interaction">{{
                                    trans('Interaktionstyp')
                                }}</Label>
                                <select
                                    id="interaction"
                                    v-model="fields.interaction"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option value="terminal">
                                        {{ trans('Terminal') }}
                                    </option>
                                    <option value="scenario">
                                        {{ trans('Szenario') }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5 sm:col-span-2">
                                <Label for="themenfeld">{{
                                    trans('Themenfeld')
                                }}</Label>
                                <div class="flex gap-2">
                                    <select
                                        id="themenfeld"
                                        v-model.number="themenfeldId"
                                        :disabled="!can_manage"
                                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                    >
                                        <option
                                            v-for="themenfeld in themenfelder"
                                            :key="themenfeld.id"
                                            :value="themenfeld.id"
                                        >
                                            {{ themenfeld.slug }}
                                        </option>
                                    </select>
                                    <Button
                                        v-if="can_manage"
                                        type="button"
                                        variant="outline"
                                        @click="saveThemenfeld"
                                    >
                                        {{ trans('Übernehmen') }}
                                    </Button>
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        trans(
                                            'Wirkt sofort, ist kein Teil des Entwurfs unten.',
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Lernkontext')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-1.5">
                            <Label>{{ trans('Skills') }}</Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="skill in skills_catalog"
                                    :key="skill"
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-sm"
                                    :class="
                                        fields.skills.includes(skill)
                                            ? 'bg-primary text-primary-foreground border-primary'
                                            : 'border-input bg-background'
                                    "
                                    @click="toggleSkill(skill)"
                                >
                                    {{ skill }}
                                </button>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="related_lessons">{{
                                trans(
                                    'Verwandte Lektionen (IDs, kommagetrennt)',
                                )
                            }}</Label>
                            <Input
                                id="related_lessons"
                                :model-value="relatedLessonsText.get()"
                                @update:model-value="
                                    (value) =>
                                        relatedLessonsText.set(String(value))
                                "
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Briefing')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RichContentWorkbench
                            v-model="fields.rich_content.briefing"
                            :glossary-terms="glossary"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">
                            {{
                                trans(
                                    'Wird angezeigt, bevor der Lernende die Herausforderung beginnt.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Hints')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="(hint, index) in fields.hints"
                            :key="index"
                            draggable="true"
                            class="cursor-grab space-y-3 rounded-md border p-3"
                            @dragstart="onHintDragStart(index)"
                            @dragover.prevent
                            @drop="onHintDrop(index)"
                        >
                            <div class="flex items-center gap-3">
                                <GripVertical
                                    class="text-muted-foreground size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <Badge variant="outline">{{ index + 1 }}</Badge>
                                <div class="flex-1 space-y-1">
                                    <Label
                                        :for="`hint-id-${index}`"
                                        class="text-xs"
                                        >{{ trans('ID') }}</Label
                                    >
                                    <Input
                                        :id="`hint-id-${index}`"
                                        :model-value="hint.id"
                                        @update:model-value="
                                            (value) =>
                                                renameHintId(
                                                    index,
                                                    String(value),
                                                )
                                        "
                                    />
                                </div>
                                <div class="w-28 space-y-1">
                                    <Label
                                        :for="`hint-cost-${index}`"
                                        class="text-xs"
                                        >{{ trans('Kosten') }}</Label
                                    >
                                    <Input
                                        :id="`hint-cost-${index}`"
                                        v-model.number="hint.cost"
                                        type="number"
                                        min="0"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeHint(index)"
                                >
                                    {{ trans('Löschen') }}
                                </Button>
                            </div>
                            <RichContentWorkbench
                                v-model="fields.rich_content.hints[hint.id]"
                                :glossary-terms="glossary"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addHint"
                        >
                            {{ trans('+ Hint hinzufügen') }}
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Write-up')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RichContentWorkbench
                            v-model="fields.rich_content.write_up"
                            :glossary-terms="glossary"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">
                            {{
                                trans(
                                    'Wird automatisch gezeigt, sobald die Herausforderung gelöst ist.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <div class="flex flex-wrap items-center gap-3 border-t pt-6">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="validating"
                        @click="runValidation"
                    >
                        {{ trans('Prüfen') }}
                    </Button>
                    <Button type="button" :disabled="saving" @click="saveDraft">
                        {{ trans('Entwurf speichern') }}
                    </Button>
                    <Button
                        v-if="
                            pending_version &&
                            pending_version.status === 'draft'
                        "
                        type="button"
                        variant="secondary"
                        :disabled="acting"
                        @click="submitForReview"
                    >
                        {{ trans('Zur Prüfung einreichen') }}
                    </Button>
                    <Button
                        v-if="
                            pending_version &&
                            pending_version.status === 'review' &&
                            can_publish
                        "
                        type="button"
                        :disabled="acting"
                        @click="publishVersion"
                    >
                        {{ trans('Freigeben') }}
                    </Button>
                </div>
            </div>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Runtime')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-muted-foreground text-sm">
                            {{
                                trans(
                                    'Container-Runtime, Engine-Konfiguration und Flag-Validierung sind schreibgeschützt und werden von Administratoren verwaltet.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Versionen')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p
                            v-if="versions.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            {{ trans('Noch keine Versionshistorie.') }}
                        </p>
                        <div
                            v-for="version in versions"
                            :key="version.id"
                            class="flex items-center justify-between text-sm"
                        >
                            <span
                                >{{ trans('v:id', { id: version.id }) }}
                                {{
                                    statusLabels[version.status] ??
                                    version.status
                                }}</span
                            >
                            <Badge
                                v-if="version.is_current"
                                variant="default"
                                >{{ trans('aktiv') }}</Badge
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="can_manage" class="border-destructive/50">
                    <CardHeader>
                        <CardTitle class="text-destructive text-base">{{
                            trans('Danger Zone')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="duplicate"
                        >
                            {{ trans('Duplizieren') }}
                        </Button>
                        <Button
                            v-if="node.status !== 'archived'"
                            type="button"
                            variant="destructive"
                            class="w-full"
                            @click="archive"
                        >
                            {{ trans('Archivieren') }}
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="restore"
                        >
                            {{ trans('Wiederherstellen') }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PageContainer>
</template>
