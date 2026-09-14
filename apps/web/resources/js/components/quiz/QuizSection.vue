<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { answer as answerRoute } from '@/routes/quiz';

export type QuizQuestion = {
    id: string;
    type: 'single' | 'multi' | 'input';
    question_html: string;
    options_html: string[];
    last_result: 'correct' | 'incorrect' | null;
};

const props = defineProps<{
    lessonId: string;
    questions: QuizQuestion[];
}>();

type AnswerState = {
    single: string;
    multi: Set<number>;
    input: string;
    feedback: 'correct' | 'wrong' | null;
    checking: boolean;
};

const state = reactive<Record<string, AnswerState>>(
    Object.fromEntries(
        props.questions.map((q) => [
            q.id,
            {
                single: '',
                multi: new Set<number>(),
                input: '',
                feedback: null,
                checking: false,
            },
        ]),
    ),
);

function toggleMulti(questionId: string, index: number) {
    const s = state[questionId];
    if (s.multi.has(index)) {
        s.multi.delete(index);
    } else {
        s.multi.add(index);
    }
}

async function checkAnswer(question: QuizQuestion) {
    const s = state[question.id];
    let value: string | number | number[];

    if (question.type === 'single') {
        value = Number(s.single);
    } else if (question.type === 'multi') {
        value = Array.from(s.multi);
    } else {
        value = s.input;
    }

    s.checking = true;
    try {
        const result = await postJson<{ correct: boolean }>(
            answerRoute.url({
                lesson: props.lessonId,
                questionId: question.id,
            }),
            { value },
        );
        s.feedback = result.correct ? 'correct' : 'wrong';
    } finally {
        s.checking = false;
    }
}
</script>

<template>
    <section
        v-if="questions.length"
        class="knowledge-check"
        aria-label="Wissen prüfen"
    >
        <p class="knowledge-check-title">
            <CircleHelp class="size-4" aria-hidden="true" />
            {{ trans('Wissen prüfen') }}
        </p>
        <div class="knowledge-check-questions">
            <div
                v-for="question in questions"
                :key="question.id"
                class="knowledge-check-question"
            >
                <p
                    class="text-sm font-medium"
                    v-html="question.question_html"
                />

                <RadioGroup
                    v-if="question.type === 'single'"
                    v-model="state[question.id].single"
                    class="space-y-2"
                >
                    <div
                        v-for="(option, index) in question.options_html"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <RadioGroupItem
                            :id="`${question.id}-${index}`"
                            :value="String(index)"
                        />
                        <Label
                            :for="`${question.id}-${index}`"
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
                            :id="`${question.id}-${index}`"
                            :model-value="state[question.id].multi.has(index)"
                            @update:model-value="
                                () => toggleMulti(question.id, index)
                            "
                        />
                        <Label
                            :for="`${question.id}-${index}`"
                            class="text-sm font-normal"
                            v-html="option"
                        />
                    </div>
                </div>

                <Input
                    v-else
                    v-model="state[question.id].input"
                    :placeholder="trans('Antwort eingeben')"
                    class="font-mono text-sm"
                />

                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="state[question.id].checking"
                        @click="checkAnswer(question)"
                    >
                        {{ trans('Prüfen') }}
                    </Button>
                    <span
                        v-if="state[question.id].feedback === 'correct'"
                        class="text-sm text-green-600"
                    >
                        {{ trans('Richtig!') }}
                    </span>
                    <span
                        v-else-if="state[question.id].feedback === 'wrong'"
                        class="text-destructive text-sm"
                    >
                        {{ trans('Leider falsch.') }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
