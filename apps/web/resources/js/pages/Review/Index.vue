<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { dashboard } from '@/routes';
import { answer as answerRoute } from '@/routes/quiz';

type ReviewQuestion = {
    id: string;
    type: 'single' | 'multi' | 'input';
    question_html: string;
    options_html: string[];
};

type ReviewCard = {
    lesson_id: string;
    lesson_title: string;
    question: ReviewQuestion;
};

const props = defineProps<{
    cards: ReviewCard[];
}>();

const remaining = ref<ReviewCard[]>([...props.cards]);

type AnswerState = {
    single: string;
    multi: Set<number>;
    input: string;
    feedback: 'correct' | 'wrong' | null;
    checking: boolean;
};

function freshState(): AnswerState {
    return { single: '', multi: new Set<number>(), input: '', feedback: null, checking: false };
}

const state = reactive<Record<string, AnswerState>>(
    Object.fromEntries(props.cards.map((c) => [c.lesson_id + ':' + c.question.id, freshState()])),
);

function key(card: ReviewCard): string {
    return card.lesson_id + ':' + card.question.id;
}

function toggleMulti(card: ReviewCard, index: number) {
    const s = state[key(card)];
    if (s.multi.has(index)) {
        s.multi.delete(index);
    } else {
        s.multi.add(index);
    }
}

async function checkAnswer(card: ReviewCard) {
    const s = state[key(card)];
    let value: string | number | number[];

    if (card.question.type === 'single') {
        value = Number(s.single);
    } else if (card.question.type === 'multi') {
        value = Array.from(s.multi);
    } else {
        value = s.input;
    }

    s.checking = true;
    try {
        const result = await postJson<{ correct: boolean }>(
            answerRoute.url({ lesson: card.lesson_id, questionId: card.question.id }),
            { value },
        );
        s.feedback = result.correct ? 'correct' : 'wrong';
    } finally {
        s.checking = false;
    }
}

function next(card: ReviewCard) {
    remaining.value = remaining.value.filter((c) => c !== card);
}
</script>

<template>
    <Head :title="trans('Wiederholung')" />

    <div class="mx-auto max-w-2xl space-y-6 p-4">
        <h1 class="text-2xl font-semibold">{{ trans('Fällige Wiederholungen') }}</h1>

        <p v-if="remaining.length === 0" class="text-muted-foreground text-sm">
            {{ trans('Für heute fertig — keine fälligen Karten mehr.') }}
        </p>

        <Card v-for="card in remaining" :key="key(card)">
            <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">{{ card.lesson_title }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-sm font-medium" v-html="card.question.question_html" />

                <RadioGroup
                    v-if="card.question.type === 'single'"
                    v-model="state[key(card)].single"
                    class="space-y-2"
                >
                    <div
                        v-for="(option, index) in card.question.options_html"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <RadioGroupItem :id="`${key(card)}-${index}`" :value="String(index)" />
                        <Label :for="`${key(card)}-${index}`" class="text-sm font-normal" v-html="option" />
                    </div>
                </RadioGroup>

                <div v-else-if="card.question.type === 'multi'" class="space-y-2">
                    <div
                        v-for="(option, index) in card.question.options_html"
                        :key="index"
                        class="flex items-center gap-2"
                    >
                        <Checkbox
                            :id="`${key(card)}-${index}`"
                            :model-value="state[key(card)].multi.has(index)"
                            @update:model-value="() => toggleMulti(card, index)"
                        />
                        <Label :for="`${key(card)}-${index}`" class="text-sm font-normal" v-html="option" />
                    </div>
                </div>

                <Input
                    v-else
                    v-model="state[key(card)].input"
                    :placeholder="trans('Antwort eingeben')"
                    class="font-mono text-sm"
                />

                <div class="flex items-center gap-3">
                    <Button
                        v-if="state[key(card)].feedback === null"
                        type="button"
                        size="sm"
                        :disabled="state[key(card)].checking"
                        @click="checkAnswer(card)"
                    >
                        {{ trans('Prüfen') }}
                    </Button>
                    <Button v-else type="button" size="sm" variant="outline" @click="next(card)">
                        {{ trans('Weiter') }}
                    </Button>
                    <span
                        v-if="state[key(card)].feedback === 'correct'"
                        class="text-sm text-green-600"
                    >
                        {{ trans('Richtig!') }}
                    </span>
                    <span
                        v-else-if="state[key(card)].feedback === 'wrong'"
                        class="text-destructive text-sm"
                    >
                        {{ trans('Leider falsch.') }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <Link :href="dashboard()" class="text-muted-foreground text-sm underline-offset-2 hover:underline">
            {{ trans('Zurück zum Dashboard') }}
        </Link>
    </div>
</template>
