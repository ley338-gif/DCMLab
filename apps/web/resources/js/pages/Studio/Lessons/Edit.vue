<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RichContentWorkbench from '@/components/RichContent/RichContentWorkbench.vue';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { index as studioIndex } from '@/routes/studio';
import {
    index as studioLessonsIndex,
    update,
    validate,
} from '@/routes/studio/lessons';
import { show as showLessonElements } from '@/routes/studio/lessons/elements';
import { publish, submit } from '@/routes/author/quiz-versions';
import type { RichContentDocument } from '@/types/richContent';
import type { GlossaryTermOption } from '@/lib/richContent/slashCommand';

type SandboxFields = {
    required: boolean;
    dataset: string | null;
    note: string | null;
};

type RelatedNodeFields = {
    node: string | null;
    optional: boolean;
};

type LessonFields = {
    title: string;
    teaser: string;
    level: 'einsteiger' | 'aufbau' | 'fortgeschritten';
    duration_minutes: number;
    tools: string[];
    requires: string[];
    glossary_terms: string[];
    objectives: string[];
    sandbox: SandboxFields;
    related_node: RelatedNodeFields;
    rich_content: RichContentDocument;
};

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
} | null;

const props = defineProps<{
    lesson: { lesson_id: string; title: string };
    fields: LessonFields;
    catalog: {
        tools: string[];
        glossary_terms: string[];
        glossary: GlossaryTermOption[];
        datasets: string[];
        nodes: string[];
    };
    pending_version: PendingVersion;
    can_publish: boolean;
    preview_url: string;
}>();

const fields = ref<LessonFields>({ ...props.fields });
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

/**
 * Leichtgewichtiger "ungespeichert"-Hinweis (Plan §22) -- vergleicht den
 * aktuellen Stand gegen den zuletzt erfolgreich gespeicherten Schnappschuss,
 * kein neuer Speicher-/Autosave-Mechanismus.
 */
const lastSavedSnapshot = ref(JSON.stringify(props.fields));
const hasUnsavedChanges = computed(
    () => JSON.stringify(fields.value) !== lastSavedSnapshot.value,
);

function listModel(key: 'tools' | 'requires' | 'glossary_terms') {
    return {
        get(): string {
            return fields.value[key].join(', ');
        },
        set(value: string) {
            fields.value[key] = value
                .split(',')
                .map((part) => part.trim())
                .filter((part) => part !== '');
        },
    };
}

const objectivesText = {
    get(): string {
        return fields.value.objectives.join('\n');
    },
    set(value: string) {
        fields.value.objectives = value
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '');
    },
};

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validate.url({ lesson: props.lesson.lesson_id }),
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
    router.post(update.url({ lesson: props.lesson.lesson_id }), fields.value, {
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

const statusLabels: Record<string, string> = {
    draft: trans('Entwurf'),
    review: trans('Zur Prüfung eingereicht'),
    published: trans('Veröffentlicht'),
};
</script>

<template>
    <Head
        :title="trans('Lektion bearbeiten: :title', { title: lesson.title })"
    />

    <PageContainer wide>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Lessons'), href: studioLessonsIndex() },
                { title: lesson.title, href: '' },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{
                    trans('Lektion bearbeiten: :title', { title: lesson.title })
                }}
            </h1>
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground text-xs">
                    {{
                        hasUnsavedChanges
                            ? trans('Ungespeicherte Änderungen')
                            : trans('Gespeichert')
                    }}
                </span>
                <Link
                    :href="showLessonElements(lesson.lesson_id)"
                    class="text-muted-foreground text-sm underline-offset-4 hover:underline"
                >
                    {{ trans('Elementreihenfolge ansehen') }}
                </Link>
                <Badge v-if="pending_version" variant="outline">
                    {{ statusLabels[pending_version.status] }}
                </Badge>
                <a :href="preview_url" target="_blank" rel="noopener">
                    <Button type="button" variant="outline">{{
                        trans('Vorschau')
                    }}</Button>
                </a>
                <a
                    :href="showLesson(lesson.lesson_id).url"
                    target="_blank"
                    rel="noopener"
                >
                    <Button type="button" variant="outline">{{
                        trans('Im Lernpfad testen')
                    }}</Button>
                </a>
            </div>
        </div>

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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Metadaten')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="title">{{ trans('Titel') }}</Label>
                        <Input id="title" v-model="fields.title" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="teaser">{{ trans('Teaser') }}</Label>
                        <Input id="teaser" v-model="fields.teaser" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="level">{{ trans('Niveau') }}</Label>
                        <select
                            id="level"
                            v-model="fields.level"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option value="einsteiger">
                                {{ trans('Einsteiger') }}
                            </option>
                            <option value="aufbau">
                                {{ trans('Aufbau') }}
                            </option>
                            <option value="fortgeschritten">
                                {{ trans('Fortgeschritten') }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="duration">{{
                            trans('Dauer (Minuten)')
                        }}</Label>
                        <Input
                            id="duration"
                            v-model.number="fields.duration_minutes"
                            type="number"
                            min="1"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <Label for="tools">{{
                        trans('Werkzeuge (kommagetrennt)')
                    }}</Label>
                    <Input
                        id="tools"
                        :model-value="listModel('tools').get()"
                        @update:model-value="
                            (value) => listModel('tools').set(String(value))
                        "
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ trans('Verfügbar') }}: {{ catalog.tools.join(', ') }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="requires">{{
                        trans('Voraussetzungen (Lektions-IDs, kommagetrennt)')
                    }}</Label>
                    <Input
                        id="requires"
                        :model-value="listModel('requires').get()"
                        @update:model-value="
                            (value) => listModel('requires').set(String(value))
                        "
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="glossary_terms">{{
                        trans('Glossarbegriffe (kommagetrennt)')
                    }}</Label>
                    <Input
                        id="glossary_terms"
                        :model-value="listModel('glossary_terms').get()"
                        @update:model-value="
                            (value) =>
                                listModel('glossary_terms').set(String(value))
                        "
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ trans('Verfügbar') }}:
                        {{ catalog.glossary_terms.join(', ') }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="objectives">{{
                        trans('Lernziele (eine Zeile je Ziel)')
                    }}</Label>
                    <textarea
                        id="objectives"
                        :value="objectivesText.get()"
                        rows="4"
                        class="border-input bg-background w-full rounded-md border p-3 text-sm shadow-xs"
                        @input="
                            (event) =>
                                objectivesText.set(
                                    (event.target as HTMLTextAreaElement).value,
                                )
                        "
                    />
                </div>
            </CardContent>
        </Card>

        <Card class="mt-4">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Spielwiese und verknüpfte Node')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="sandbox_required"
                        :model-value="fields.sandbox.required"
                        @update:model-value="
                            (value) =>
                                (fields.sandbox.required = value === true)
                        "
                    />
                    <Label for="sandbox_required">{{
                        trans('Spielwiese für diese Lektion nötig')
                    }}</Label>
                </div>
                <div
                    v-if="fields.sandbox.required"
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="space-y-1.5">
                        <Label for="sandbox_dataset">{{
                            trans('Datensatz')
                        }}</Label>
                        <select
                            id="sandbox_dataset"
                            v-model="fields.sandbox.dataset"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option :value="null">
                                {{ trans('(kein Datensatz)') }}
                            </option>
                            <option
                                v-for="dataset in catalog.datasets"
                                :key="dataset"
                                :value="dataset"
                            >
                                {{ dataset }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="sandbox_note">{{
                            trans('Hinweis (optional)')
                        }}</Label>
                        <Input
                            id="sandbox_note"
                            :model-value="fields.sandbox.note ?? ''"
                            @update:model-value="
                                (value) =>
                                    (fields.sandbox.note =
                                        String(value) || null)
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-4 border-t pt-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="related_node">{{
                            trans('Verknüpfte Node')
                        }}</Label>
                        <select
                            id="related_node"
                            v-model="fields.related_node.node"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option :value="null">
                                {{ trans('(keine Node)') }}
                            </option>
                            <option
                                v-for="node in catalog.nodes"
                                :key="node"
                                :value="node"
                            >
                                {{ node }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center gap-2 self-end">
                        <Checkbox
                            id="related_node_optional"
                            :model-value="fields.related_node.optional"
                            @update:model-value="
                                (value) =>
                                    (fields.related_node.optional =
                                        value === true)
                            "
                        />
                        <Label for="related_node_optional">{{
                            trans(
                                'Lektion gilt auch ohne die verknüpfte Node als abgeschlossen',
                            )
                        }}</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="mt-4">
            <h2 class="mb-3 text-base font-semibold">
                {{ trans('Lektionstext') }}
            </h2>
            <RichContentWorkbench
                v-model="fields.rich_content"
                :glossary-terms="catalog.glossary"
            />
            <p class="text-muted-foreground mt-2 text-xs">
                {{
                    trans(
                        'Der Quiz-Abschnitt am Ende der Lektion wird hier nicht angezeigt -- er bleibt beim Speichern unangetastet und wird im Quiz-Editor bearbeitet. Ein Verweis auf die naechste Lektion ("Als Naechstes: ...") gehoert dagegen hierher, ganz am Ende.',
                    )
                }}
            </p>
        </div>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t pt-6">
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
                v-if="pending_version && pending_version.status === 'draft'"
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
    </PageContainer>
</template>
