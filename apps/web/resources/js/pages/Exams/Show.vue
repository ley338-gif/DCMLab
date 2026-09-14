<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { show as showTrack } from '@/routes/tracks';
import { answer as answerRoute, show as showExam } from '@/routes/tracks/exam';

type ExamQuestion = {
    id: string;
    type: 'single' | 'multi' | 'truefalse' | 'input';
    question_html: string;
    options_html: string[];
};

type ReviewTarget = { lesson: string; anchor: string };

type AnswerFeedback = {
    correct: boolean;
    explanation_html: string | null;
    review: ReviewTarget[];
};

const props = defineProps<{
    track: { slug: string; title_key: string };
    attempt: { id: number; current_index: number; total: number };
    question: ExamQuestion;
}>();

const single = ref('');
const multi = ref(new Set<number>());
const input = ref('');
const truefalse = ref<'true' | 'false' | ''>('');
const checking = ref(false);
const feedback = ref<AnswerFeedback | null>(null);

function toggleMulti(index: number) {
    if (multi.value.has(index)) {
        multi.value.delete(index);
    } else {
        multi.value.add(index);
    }
}

async function checkAnswer() {
    let value: string | number | number[] | boolean;

    if (props.question.type === 'single') {
        value = Number(single.value);
    } else if (props.question.type === 'multi') {
        value = Array.from(multi.value);
    } else if (props.question.type === 'truefalse') {
        value = truefalse.value === 'true';
    } else {
        value = input.value;
    }

    checking.value = true;
    try {
        feedback.value = await postJson<AnswerFeedback>(
            answerRoute.url({
                track: props.track.slug,
                attempt: props.attempt.id,
            }),
            { value },
        );
    } finally {
        checking.value = false;
    }
}

function next() {
    router.visit(
        showExam({ track: props.track.slug, attempt: props.attempt.id }),
    );
}

function lessonHref(target: ReviewTarget): string {
    return `${showLesson(target.lesson).url}#${target.anchor}`;
}
</script>

<template>
    <Head :title="trans('Abschlussprüfung')" />

    <div class="mx-auto max-w-2xl space-y-6 p-4">
        <p class="text-muted-foreground text-sm">
            {{
                trans('Frage :current von :total', {
                    current: attempt.current_index + 1,
                    total: attempt.total,
                })
            }}
        </p>

        <Card>
            <CardHeader>
                <CardTitle class="text-base" v-html="question.question_html" />
            </CardHeader>
            <CardContent class="space-y-4">
                <RadioGroup
                    v-if="question.type === 'single'"
                    v-model="single"
                    class="space-y-2"
                >
                    <div
                        v-for="(option, index) in question.options_html"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <RadioGroupItem
                            :id="`opt-${index}`"
                            :value="String(index)"
                        />
                        <Label
                            :for="`opt-${index}`"
                            class="text-sm font-normal"
                            v-html="option"
                        />
                    </div>
                </RadioGroup>

                <div v-else-if="question.type === 'multi'" class="space-y-2">
                    <div
                        v-for="(option, index) in question.options_html"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`opt-${index}`"
                            :model-value="multi.has(index)"
                            @update:model-value="() => toggleMulti(index)"
                        />
                        <Label
                            :for="`opt-${index}`"
                            class="text-sm font-normal"
                            v-html="option"
                        />
                    </div>
                </div>

                <RadioGroup
                    v-else-if="question.type === 'truefalse'"
                    v-model="truefalse"
                    class="space-y-2"
                >
                    <div class="flex items-center gap-2">
                        <RadioGroupItem id="opt-true" value="true" />
                        <Label for="opt-true" class="text-sm font-normal">{{
                            trans('Richtig')
                        }}</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <RadioGroupItem id="opt-false" value="false" />
                        <Label for="opt-false" class="text-sm font-normal">{{
                            trans('Falsch')
                        }}</Label>
                    </div>
                </RadioGroup>

                <Input
                    v-else
                    v-model="input"
                    :placeholder="trans('Antwort eingeben')"
                    class="font-mono text-sm"
                />

                <div v-if="feedback === null" class="flex items-center gap-3">
                    <Button
                        type="button"
                        :disabled="checking"
                        @click="checkAnswer"
                    >
                        {{ trans('Prüfen') }}
                    </Button>
                </div>

                <div v-else class="space-y-3">
                    <p
                        class="text-sm font-medium"
                        :class="
                            feedback.correct
                                ? 'text-green-600'
                                : 'text-destructive'
                        "
                    >
                        {{
                            feedback.correct
                                ? trans('Richtig!')
                                : trans('Leider falsch.')
                        }}
                    </p>
                    <p
                        v-if="feedback.explanation_html"
                        class="text-sm"
                        v-html="feedback.explanation_html"
                    />
                    <ul
                        v-if="feedback.review.length > 0"
                        class="text-muted-foreground space-y-1 text-sm"
                    >
                        <li
                            v-for="target in feedback.review"
                            :key="target.lesson + target.anchor"
                        >
                            <a
                                :href="lessonHref(target)"
                                class="underline-offset-2 hover:underline"
                            >
                                {{
                                    trans('Nachlesen: :lesson', {
                                        lesson: target.lesson,
                                    })
                                }}
                            </a>
                        </li>
                    </ul>
                    <Button type="button" @click="next">
                        {{ trans('Weiter') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Link
            :href="showTrack(track.slug)"
            class="text-muted-foreground text-sm underline-offset-2 hover:underline"
        >
            {{ trans('Prüfung später fortsetzen') }}
        </Link>
    </div>
</template>
