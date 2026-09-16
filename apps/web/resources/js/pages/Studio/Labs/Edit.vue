<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RichContentEditor from '@/components/RichContent/RichContentEditor.vue';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import type { RichContentDocument } from '@/types/richContent';
import { index as labsIndex } from '@/routes/studio/labs';
import { publish, submit } from '@/routes/author/quiz-versions';
import {
    archive as archiveLab,
    restore as restoreLab,
    update as updateLab,
    validate as validateLab,
} from '@/routes/studio/labs';

type Difficulty = 'easy' | 'medium' | 'hard' | 'insane';
type Status = 'draft' | 'published' | 'archived';

/**
 * Nur EIN Assertion-Typ heute (CMS-8c, Betreiber-Korrektur): `prefix` ist
 * das einzige Autorenfeld, `type` wird beim Hinzufuegen implizit gesetzt.
 * Ein zweiter Typ (CMS-8d/8e) macht daraus eine echte Union und ein
 * Typ-Select je Zeile -- bewusst noch nicht jetzt, siehe Plan Abschnitt B.
 */
type Assertion = { type: 'command_executed'; prefix: string };

type LabFields = {
    title: string;
    scenario_title: string;
    difficulty: Difficulty;
    points: number;
    estimated_minutes: number;
    runtime_template: string | null;
    dataset: string | null;
    assertions: Assertion[];
    rich_content: RichContentDocument;
};

type TemplateOption = { slug: string; name: string; available: boolean };
type DatasetOption = { slug: string; available: boolean };

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
    lab: {
        slug: string;
        title: string;
        status: Status;
    };
    fields: LabFields;
    sandbox_templates: TemplateOption[];
    datasets: DatasetOption[];
    pending_version: PendingVersion;
    versions: VersionRow[];
    can_manage: boolean;
    can_publish: boolean;
}>();

const fields = ref<LabFields>({ ...props.fields });
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

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

function addAssertion() {
    fields.value.assertions = [
        ...fields.value.assertions,
        { type: 'command_executed', prefix: '' },
    ];
}

function removeAssertion(index: number) {
    fields.value.assertions = fields.value.assertions.filter((_, i) => i !== index);
}

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validateLab.url({ lab: props.lab.slug }),
            fields.value,
        );
        issues.value = result.issues;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    router.patch(updateLab.url({ lab: props.lab.slug }), fields.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        onSuccess: () => runValidation(),
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

function archive() {
    router.post(
        archiveLab.url({ lab: props.lab.slug }),
        {},
        { preserveScroll: true },
    );
}

function restore() {
    router.post(
        restoreLab.url({ lab: props.lab.slug }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="trans('Lab bearbeiten: :title', { title: lab.title })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Labs'), href: labsIndex() },
                { title: lab.title, href: '' },
            ]"
        />

        <div class="mb-6 flex items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ lab.title }}</h1>
            <Badge :variant="lab.status === 'published' ? 'default' : 'outline'">
                {{
                    lab.status === 'published'
                        ? trans('Veröffentlicht')
                        : lab.status === 'archived'
                          ? trans('Archiviert')
                          : trans('Entwurf')
                }}
            </Badge>
            <Badge v-if="pending_version" variant="secondary">
                {{ statusLabels[pending_version.status] }}
            </Badge>
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

        <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Allgemein')
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
                                <Input :model-value="lab.slug" disabled />
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        trans(
                                            'Fachlicher Schlüssel und URL — nicht änderbar.',
                                        )
                                    }}
                                </p>
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
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Runtime')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="runtime_template">{{
                                    trans('Runtime-Vorlage')
                                }}</Label>
                                <select
                                    id="runtime_template"
                                    v-model="fields.runtime_template"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option :value="null">
                                        {{ trans('-- keine Wahl --') }}
                                    </option>
                                    <option
                                        v-for="template in sandbox_templates"
                                        :key="template.slug"
                                        :value="template.slug"
                                        :disabled="!template.available"
                                    >
                                        {{ template.name }}{{
                                            template.available
                                                ? ''
                                                : ` (${trans('nicht mehr verfügbar')})`
                                        }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="dataset">{{
                                    trans('Datensatz')
                                }}</Label>
                                <select
                                    id="dataset"
                                    v-model="fields.dataset"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option :value="null">
                                        {{ trans('-- keine Wahl --') }}
                                    </option>
                                    <option
                                        v-for="dataset in datasets"
                                        :key="dataset.slug"
                                        :value="dataset.slug"
                                        :disabled="!dataset.available"
                                    >
                                        {{ dataset.slug }}{{
                                            dataset.available
                                                ? ''
                                                : ` (${trans('nicht mehr verfügbar')})`
                                        }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            {{
                                trans(
                                    'Für eine Veröffentlichung erforderlich — ein Entwurf lässt sich auch ohne diese Angaben zwischenspeichern.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Aufgabe')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RichContentEditor
                            v-model="fields.rich_content"
                            class="border-input bg-background min-h-32 w-full rounded-md border p-3 text-sm shadow-xs"
                        />
                        <p class="text-muted-foreground mt-2 text-xs">
                            {{
                                trans(
                                    'Briefing, das der Lernende vor dem Start des Labs sieht.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Erfolgskriterien')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-for="(assertion, index) in fields.assertions"
                            :key="index"
                            class="space-y-3 rounded-md border p-3"
                        >
                            <div class="flex items-end gap-3">
                                <div class="flex-1 space-y-1">
                                    <Label
                                        :for="`assertion-prefix-${index}`"
                                        class="text-xs"
                                    >
                                        {{
                                            trans(
                                                'Befehl muss ausgeführt worden sein mit Präfix',
                                            )
                                        }}
                                    </Label>
                                    <Input
                                        :id="`assertion-prefix-${index}`"
                                        v-model="assertion.prefix"
                                        :placeholder="trans('z. B. echoscu 127.0.0.1 4242 -aec ORTHANC')"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeAssertion(index)"
                                >
                                    {{ trans('Löschen') }}
                                </Button>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addAssertion"
                        >
                            {{ trans('+ Erfolgskriterium hinzufügen') }}
                        </Button>
                        <p class="text-muted-foreground text-xs">
                            {{
                                trans(
                                    'Mindestens ein Eintrag ist für eine Veröffentlichung erforderlich. Der Präfix sollte konkret genug sein, um den beabsichtigten Befehl eindeutig zu belegen.',
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
                            v-if="lab.status !== 'archived'"
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
