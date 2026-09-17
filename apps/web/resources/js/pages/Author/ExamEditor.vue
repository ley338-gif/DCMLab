<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { edit, store, validate } from '@/routes/author/exams/edit';
import { publish, submit } from '@/routes/author/quiz-versions';
import { index as tracksIndex, show as showTrack } from '@/routes/tracks';

type ReviewTarget = { lesson: string; anchor: string };

type QuestionType = 'single' | 'multi' | 'truefalse' | 'input';

type ExamQuestion = {
    id: string;
    is_ref: boolean;
    ref_lesson?: string | null;
    ref_question?: string | null;
    type?: QuestionType;
    question?: string;
    options?: string[];
    answer?: number | boolean | string | number[];
    explanation?: string;
    lesson: string;
    review: ReviewTarget[];
    difficulty: 1 | 2 | 3;
    tags: string[];
};

type ExamFields = {
    title: string;
    intro: string;
    pass_percent: number;
    draw: number;
    duration_minutes: number;
    shuffle: boolean;
    min_per_lesson: number;
    questions: ExamQuestion[];
};

type TypeShare = { type: string; percent: number; min: number; max: number };

type Coverage = {
    pool_size: number;
    min_per_lesson: number;
    lessons: { lesson_id: string; count: number; ok: boolean }[];
    cross_count: number;
    cross_ok: boolean;
    type_shares: TypeShare[];
    difficulty3_share: number;
};

type Catalog = {
    lessons: string[];
    lesson_headings: Record<string, string[]>;
    lesson_quiz_questions: Record<string, string[]>;
    tags: string[];
};

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
} | null;

const props = defineProps<{
    track: { slug: string; title_key: string };
    fields: ExamFields;
    coverage: Coverage;
    catalog: Catalog;
    pending_version: PendingVersion;
    can_publish: boolean;
}>();

const fields = ref<ExamFields>({
    ...props.fields,
    questions: [...props.fields.questions],
});
const coverage = ref<Coverage>(props.coverage);
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

const lessonChoices = computed(() => [...props.catalog.lessons, 'cross']);

function payload() {
    return { ...fields.value };
}

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[]; coverage: Coverage }>(
            validate.url({ track: props.track.slug }),
            payload(),
        );
        issues.value = result.issues;
        coverage.value = result.coverage;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    router.post(store.url({ track: props.track.slug }), payload(), {
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

const statusLabels: Record<string, string> = {
    draft: trans('Entwurf'),
    review: trans('Zur Prüfung eingereicht'),
    published: trans('Veröffentlicht'),
};

// --- Fragenpool (ADR 0090) ---------------------------------------------

const editingIndex = ref<number | null>(null);
const isNewQuestion = ref(false);

function blankQuestion(): ExamQuestion {
    return {
        id: '',
        is_ref: false,
        type: 'single',
        question: '',
        options: ['', ''],
        answer: 0,
        explanation: '',
        lesson: lessonChoices.value[0] ?? 'cross',
        review: [{ lesson: lessonChoices.value[0] ?? '', anchor: '' }],
        difficulty: 1,
        tags: [],
    };
}

const draftQuestion = ref<ExamQuestion>(blankQuestion());
const optionsText = computed({
    get: () => (draftQuestion.value.options ?? []).join('\n'),
    set: (value: string) => {
        draftQuestion.value.options = value
            .split('\n')
            .map((line) => line.trim());
    },
});
const tagsText = computed({
    get: () => draftQuestion.value.tags.join(', '),
    set: (value: string) => {
        draftQuestion.value.tags = value
            .split(',')
            .map((tag) => tag.trim())
            .filter((tag) => tag !== '');
    },
});

function startAdd() {
    draftQuestion.value = blankQuestion();
    isNewQuestion.value = true;
    // Ein Platzhalter muss sofort in die Liste, sonst hat das v-for unten
    // (das die Bearbeitungsform je Zeile rendert) keine Zeile, in die es
    // die neue Frage zeichnen könnte.
    editingIndex.value = fields.value.questions.push(draftQuestion.value) - 1;
}

function startEdit(index: number) {
    draftQuestion.value = JSON.parse(
        JSON.stringify(fields.value.questions[index]),
    );
    isNewQuestion.value = false;
    editingIndex.value = index;
}

function cancelEdit() {
    if (isNewQuestion.value && editingIndex.value !== null) {
        fields.value.questions.splice(editingIndex.value, 1);
    }

    editingIndex.value = null;
}

function saveQuestion() {
    if (editingIndex.value === null) {
        return;
    }

    const question = { ...draftQuestion.value };

    if (question.is_ref) {
        delete question.type;
        delete question.question;
        delete question.options;
        delete question.answer;
        delete question.explanation;
    } else {
        delete question.ref_lesson;
        delete question.ref_question;

        if (question.type === 'truefalse' || question.type === 'input') {
            delete question.options;
        }
    }

    fields.value.questions[editingIndex.value] = question;

    editingIndex.value = null;
    isNewQuestion.value = false;
    runValidation();
}

function removeQuestion(index: number) {
    fields.value.questions.splice(index, 1);
    runValidation();
}

function addReviewTarget() {
    draftQuestion.value.review.push({
        lesson: lessonChoices.value[0] ?? '',
        anchor: '',
    });
}

function removeReviewTarget(index: number) {
    if (draftQuestion.value.review.length > 1) {
        draftQuestion.value.review.splice(index, 1);
    }
}

function answerAsText(question: ExamQuestion): string {
    if (question.is_ref) {
        return trans('(aus :lesson / :question übernommen)', {
            lesson: question.ref_lesson ?? '',
            question: question.ref_question ?? '',
        });
    }

    return Array.isArray(question.answer)
        ? question.answer.join(', ')
        : String(question.answer);
}

function updateSingleAnswer(value: string) {
    draftQuestion.value.answer = Number(value);
}

function updateMultiAnswer(value: string) {
    draftQuestion.value.answer = value
        .split(',')
        .map((part) => part.trim())
        .filter((part) => part !== '')
        .map(Number);
}
</script>

<template>
    <Head :title="trans('Prüfung bearbeiten: :track', { track: track.slug })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: tracksIndex() },
                { title: track.slug, href: showTrack(track.slug) },
                { title: trans('Prüfung bearbeiten'), href: edit(track.slug) },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{ trans('Prüfung bearbeiten: :track', { track: track.slug }) }}
            </h1>
            <Badge v-if="pending_version" variant="outline">
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

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Einstellungen')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="title">{{ trans('Titel') }}</Label>
                        <Input id="title" v-model="fields.title" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="intro">{{ trans('Einleitung') }}</Label>
                        <Input id="intro" v-model="fields.intro" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pass_percent">{{
                            trans('Bestehensgrenze (%)')
                        }}</Label>
                        <Input
                            id="pass_percent"
                            v-model.number="fields.pass_percent"
                            type="number"
                            min="50"
                            max="100"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="draw">{{
                            trans('Fragenzahl je Versuch')
                        }}</Label>
                        <Input
                            id="draw"
                            v-model.number="fields.draw"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="duration_minutes">{{
                            trans('Dauer (Minuten)')
                        }}</Label>
                        <Input
                            id="duration_minutes"
                            v-model.number="fields.duration_minutes"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="min_per_lesson">{{
                            trans('Mindestfragen je Lektion')
                        }}</Label>
                        <Input
                            id="min_per_lesson"
                            v-model.number="fields.min_per_lesson"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="shuffle"
                            :model-value="fields.shuffle"
                            @update:model-value="
                                (value) => (fields.shuffle = value === true)
                            "
                        />
                        <Label for="shuffle">{{
                            trans('Fragen mischen')
                        }}</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="mt-4">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Pool-Übersicht')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-muted-foreground text-xs">
                    {{
                        trans(
                            'Wird bei jedem Prüfen/Ändern gegen den aktuellen Entwurf neu berechnet.',
                        )
                    }}
                </p>
                <p class="text-sm">
                    {{ trans('Fragen im Pool') }}: {{ coverage.pool_size }}
                </p>

                <div>
                    <p class="mb-1 text-sm font-medium">
                        {{ trans('Abdeckung je Lektion') }}
                    </p>
                    <ul
                        class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4"
                    >
                        <li
                            v-for="lesson in coverage.lessons"
                            :key="lesson.lesson_id"
                            :class="
                                lesson.ok
                                    ? 'text-foreground'
                                    : 'text-destructive'
                            "
                        >
                            {{ lesson.lesson_id }}: {{ lesson.count }}/{{
                                coverage.min_per_lesson
                            }}
                        </li>
                    </ul>
                </div>

                <p
                    class="text-sm"
                    :class="
                        coverage.cross_ok
                            ? 'text-foreground'
                            : 'text-destructive'
                    "
                >
                    {{ trans('Cross-Fragen') }}: {{ coverage.cross_count }}/4
                </p>

                <div v-if="coverage.type_shares.length > 0">
                    <p class="mb-1 text-sm font-medium">
                        {{ trans('Typmischung') }}
                    </p>
                    <ul class="space-y-0.5 text-sm">
                        <li
                            v-for="share in coverage.type_shares"
                            :key="share.type"
                            :class="
                                share.percent < share.min ||
                                share.percent > share.max
                                    ? 'text-destructive'
                                    : 'text-foreground'
                            "
                        >
                            {{ share.type }}: {{ share.percent }}% ({{
                                trans('erwartet')
                            }}
                            {{ share.min }}-{{ share.max }}%)
                        </li>
                    </ul>
                </div>

                <p
                    class="text-sm"
                    :class="
                        coverage.difficulty3_share < 25
                            ? 'text-destructive'
                            : 'text-foreground'
                    "
                >
                    {{ trans('Schwierigkeit 3') }}:
                    {{ coverage.difficulty3_share }}% ({{
                        trans('erwartet mind. 25%')
                    }})
                </p>
            </CardContent>
        </Card>

        <Card class="mt-4">
            <CardHeader class="flex-row items-center justify-between space-y-0">
                <CardTitle class="text-base">{{
                    trans('Fragenpool')
                }}</CardTitle>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="editingIndex !== null"
                    @click="startAdd"
                >
                    {{ trans('Frage hinzufügen') }}
                </Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="(question, index) in fields.questions"
                    :key="question.id || index"
                    class="rounded-md border p-3 text-sm"
                >
                    <div
                        v-if="editingIndex !== index"
                        class="flex items-start justify-between gap-4"
                    >
                        <div>
                            <p class="font-medium">
                                {{ question.id }}
                                <Badge variant="outline" class="ml-1">{{
                                    question.is_ref ? 'ref' : question.type
                                }}</Badge>
                                <Badge variant="secondary" class="ml-1"
                                    >{{ trans('Schwierigkeit') }}
                                    {{ question.difficulty }}</Badge
                                >
                            </p>
                            <p class="text-muted-foreground mt-1">
                                {{
                                    question.is_ref
                                        ? `${question.ref_lesson} / ${question.ref_question}`
                                        : question.question
                                }}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ trans('Lektion') }}: {{ question.lesson }} —
                                {{ trans('Antwort') }}:
                                {{ answerAsText(question) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="editingIndex !== null"
                                @click="startEdit(index)"
                            >
                                {{ trans('Bearbeiten') }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="destructive"
                                :disabled="editingIndex !== null"
                                @click="removeQuestion(index)"
                            >
                                {{ trans('Entfernen') }}
                            </Button>
                        </div>
                    </div>

                    <div v-else class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label>{{ trans('ID') }}</Label>
                                <Input
                                    v-model="draftQuestion.id"
                                    :disabled="!isNewQuestion"
                                    placeholder="f09"
                                />
                            </div>
                            <div class="flex items-center gap-2 self-end">
                                <Checkbox
                                    :model-value="draftQuestion.is_ref"
                                    @update:model-value="
                                        (value) =>
                                            (draftQuestion.is_ref =
                                                value === true)
                                    "
                                />
                                <Label>{{
                                    trans(
                                        'Referenz auf eine Lektions-Quizfrage',
                                    )
                                }}</Label>
                            </div>
                        </div>

                        <template v-if="draftQuestion.is_ref">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label>{{ trans('Ziel-Lektion') }}</Label>
                                    <select
                                        v-model="draftQuestion.ref_lesson"
                                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                    >
                                        <option
                                            v-for="lessonId in catalog.lessons"
                                            :key="lessonId"
                                            :value="lessonId"
                                        >
                                            {{ lessonId }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label>{{ trans('Quizfrage') }}</Label>
                                    <select
                                        v-model="draftQuestion.ref_question"
                                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                    >
                                        <option
                                            v-for="questionId in catalog
                                                .lesson_quiz_questions[
                                                draftQuestion.ref_lesson ?? ''
                                            ] ?? []"
                                            :key="questionId"
                                            :value="questionId"
                                        >
                                            {{ questionId }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Fragetext') }}</Label>
                                <Input v-model="draftQuestion.question" />
                            </div>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Typ') }}</Label>
                                <select
                                    v-model="draftQuestion.type"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option value="single">
                                        {{ trans('Einfachauswahl') }}
                                    </option>
                                    <option value="multi">
                                        {{ trans('Mehrfachauswahl') }}
                                    </option>
                                    <option value="truefalse">
                                        {{ trans('Richtig/Falsch') }}
                                    </option>
                                    <option value="input">
                                        {{ trans('Freitext') }}
                                    </option>
                                </select>
                            </div>
                            <div
                                v-if="
                                    draftQuestion.type === 'single' ||
                                    draftQuestion.type === 'multi'
                                "
                                class="space-y-1.5"
                            >
                                <Label>{{
                                    trans('Optionen (eine Zeile je Option)')
                                }}</Label>
                                <textarea
                                    v-model="optionsText"
                                    rows="4"
                                    class="border-input bg-background w-full rounded-md border p-3 text-sm shadow-xs"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Antwort') }}</Label>
                                <Input
                                    v-if="draftQuestion.type === 'single'"
                                    type="number"
                                    min="0"
                                    :model-value="String(draftQuestion.answer)"
                                    @update:model-value="
                                        (value) =>
                                            updateSingleAnswer(String(value))
                                    "
                                    :placeholder="
                                        trans(
                                            '0-basierter Index der richtigen Option',
                                        )
                                    "
                                />
                                <Input
                                    v-else-if="draftQuestion.type === 'multi'"
                                    :model-value="
                                        Array.isArray(draftQuestion.answer)
                                            ? draftQuestion.answer.join(', ')
                                            : ''
                                    "
                                    @update:model-value="
                                        (value) =>
                                            updateMultiAnswer(String(value))
                                    "
                                    :placeholder="
                                        trans(
                                            '0-basierte Indizes, kommagetrennt',
                                        )
                                    "
                                />
                                <select
                                    v-else-if="
                                        draftQuestion.type === 'truefalse'
                                    "
                                    :value="String(draftQuestion.answer)"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                    @change="
                                        (event) =>
                                            (draftQuestion.answer =
                                                (
                                                    event.target as HTMLSelectElement
                                                ).value === 'true')
                                    "
                                >
                                    <option value="true">
                                        {{ trans('Richtig') }}
                                    </option>
                                    <option value="false">
                                        {{ trans('Falsch') }}
                                    </option>
                                </select>
                                <Input
                                    v-else
                                    :model-value="
                                        String(draftQuestion.answer ?? '')
                                    "
                                    @update:model-value="
                                        (value) =>
                                            (draftQuestion.answer =
                                                String(value))
                                    "
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Erklärung') }}</Label>
                                <Input v-model="draftQuestion.explanation" />
                            </div>
                        </template>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label>{{ trans('Lektion (fachlich)') }}</Label>
                                <select
                                    v-model="draftQuestion.lesson"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="lessonId in lessonChoices"
                                        :key="lessonId"
                                        :value="lessonId"
                                    >
                                        {{ lessonId }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label>{{ trans('Schwierigkeit') }}</Label>
                                <select
                                    v-model.number="draftQuestion.difficulty"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option :value="1">1</option>
                                    <option :value="2">2</option>
                                    <option :value="3">3</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label>{{
                                trans(
                                    'Tags (kommagetrennt, verfügbar: :tags)',
                                    { tags: catalog.tags.join(', ') },
                                )
                            }}</Label>
                            <Input v-model="tagsText" />
                        </div>

                        <div class="space-y-1.5">
                            <Label>{{
                                trans('Review-Anker (mind. einer)')
                            }}</Label>
                            <div
                                v-for="(
                                    target, targetIndex
                                ) in draftQuestion.review"
                                :key="targetIndex"
                                class="flex gap-2"
                            >
                                <select
                                    v-model="target.lesson"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="lessonId in catalog.lessons"
                                        :key="lessonId"
                                        :value="lessonId"
                                    >
                                        {{ lessonId }}
                                    </option>
                                </select>
                                <select
                                    v-model="target.anchor"
                                    class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                                >
                                    <option
                                        v-for="anchor in catalog
                                            .lesson_headings[target.lesson] ??
                                        []"
                                        :key="anchor"
                                        :value="anchor"
                                    >
                                        {{ anchor }}
                                    </option>
                                </select>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    :disabled="draftQuestion.review.length <= 1"
                                    @click="removeReviewTarget(targetIndex)"
                                >
                                    {{ trans('−') }}
                                </Button>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="addReviewTarget"
                            >
                                {{ trans('Weiteres Ziel') }}
                            </Button>
                        </div>

                        <div class="flex gap-2 border-t pt-3">
                            <Button
                                type="button"
                                size="sm"
                                @click="saveQuestion"
                                >{{ trans('Übernehmen') }}</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="cancelEdit"
                                >{{ trans('Abbrechen') }}</Button
                            >
                        </div>
                    </div>
                </div>

                <p
                    v-if="fields.questions.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    {{ trans('Noch keine Fragen im Pool.') }}
                </p>
            </CardContent>
        </Card>

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
