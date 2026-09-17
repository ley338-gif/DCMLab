<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { edit, store, validate } from '@/routes/author/lessons/quiz';
import { publish, submit } from '@/routes/author/quiz-versions';
import { show as showLesson } from '@/routes/lessons';
import { index as tracksIndex } from '@/routes/tracks';

type QuestionType = 'single' | 'multi' | 'input';

type QuizQuestion = {
    id: string;
    type: QuestionType;
    answer: number | number[] | string;
    question: string;
    options: string[];
};

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
} | null;

const props = defineProps<{
    lesson: { lesson_id: string; title: string };
    questions: QuizQuestion[];
    pending_version: PendingVersion;
    can_publish: boolean;
}>();

const questions = ref<QuizQuestion[]>(
    props.questions.map((question) => ({
        ...question,
        options: [...question.options],
    })),
);
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

function nextQuestionId(): string {
    const numbers = questions.value
        .map((q) => Number(q.id.replace(/^q/, '')))
        .filter((n) => !Number.isNaN(n));
    const next = numbers.length > 0 ? Math.max(...numbers) + 1 : 1;
    return `q${next}`;
}

function addQuestion() {
    questions.value.push({
        id: nextQuestionId(),
        type: 'single',
        answer: 0,
        question: '',
        options: ['', ''],
    });
}

function removeQuestion(index: number) {
    questions.value.splice(index, 1);
}

function addOption(questionIndex: number) {
    questions.value[questionIndex].options.push('');
}

function removeOption(questionIndex: number, optionIndex: number) {
    questions.value[questionIndex].options.splice(optionIndex, 1);
}

/**
 * `answer` ist je nach Typ ein Index (single), eine Liste von Indizes
 * (multi, kommagetrennt eingegeben) oder ein exakter String (input) --
 * siehe docs/content-schema.md Abschnitt 2.
 */
function answerModel(question: QuizQuestion) {
    return {
        get(): string {
            return Array.isArray(question.answer)
                ? question.answer.join(', ')
                : String(question.answer);
        },
        set(value: string) {
            if (question.type === 'multi') {
                question.answer = value
                    .split(',')
                    .map((part) => Number(part.trim()))
                    .filter((n) => !Number.isNaN(n));
            } else if (question.type === 'single') {
                question.answer = Number(value);
            } else {
                question.answer = value;
            }
        },
    };
}

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validate.url({ lesson: props.lesson.lesson_id }),
            { questions: questions.value },
        );
        issues.value = result.issues;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    router.post(
        store.url({ lesson: props.lesson.lesson_id }),
        { questions: questions.value },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
            onSuccess: () => runValidation(),
        },
    );
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
    <Head :title="trans('Quiz bearbeiten: :title', { title: lesson.title })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: tracksIndex() },
                {
                    title: lesson.title,
                    href: showLesson(lesson.lesson_id),
                },
                {
                    title: trans('Quiz bearbeiten'),
                    href: edit(lesson.lesson_id),
                },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{ trans('Quiz bearbeiten: :title', { title: lesson.title }) }}
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

        <div class="space-y-4">
            <Card v-for="(question, qIndex) in questions" :key="qIndex">
                <CardHeader>
                    <div class="flex items-center justify-between gap-2">
                        <CardTitle class="text-base">{{
                            question.id
                        }}</CardTitle>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removeQuestion(qIndex)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <CardDescription>
                        {{
                            trans(
                                'Typ, Antwort-Index (0-basiert) und Fragetext -- derselbe Regelsatz wie content:validate prüft live.',
                            )
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label :for="`type-${qIndex}`">{{
                                trans('Typ')
                            }}</Label>
                            <select
                                :id="`type-${qIndex}`"
                                v-model="question.type"
                                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="single">
                                    {{ trans('Einzelauswahl') }}
                                </option>
                                <option value="multi">
                                    {{ trans('Mehrfachauswahl') }}
                                </option>
                                <option value="input">
                                    {{ trans('Freitext') }}
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label :for="`answer-${qIndex}`">{{
                                trans('Antwort')
                            }}</Label>
                            <Input
                                :id="`answer-${qIndex}`"
                                :model-value="answerModel(question).get()"
                                @update:model-value="
                                    (value) =>
                                        answerModel(question).set(String(value))
                                "
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label :for="`question-${qIndex}`">{{
                            trans('Fragetext')
                        }}</Label>
                        <Input
                            :id="`question-${qIndex}`"
                            v-model="question.question"
                        />
                    </div>

                    <div v-if="question.type !== 'input'" class="space-y-1.5">
                        <Label>{{ trans('Optionen') }}</Label>
                        <div
                            v-for="(option, oIndex) in question.options"
                            :key="oIndex"
                            class="flex items-center gap-2"
                        >
                            <span class="text-muted-foreground w-5 text-sm"
                                >{{ oIndex }}.</span
                            >
                            <Input
                                v-model="question.options[oIndex]"
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeOption(qIndex, oIndex)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addOption(qIndex)"
                        >
                            <Plus class="size-4" />
                            {{ trans('Option hinzufügen') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Button
            type="button"
            variant="outline"
            class="mt-4"
            @click="addQuestion"
        >
            <Plus class="size-4" />
            {{ trans('Frage hinzufügen') }}
        </Button>

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
