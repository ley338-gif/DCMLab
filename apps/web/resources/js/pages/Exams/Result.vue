<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { show as showTrack } from '@/routes/tracks';
import { start as startExam } from '@/routes/tracks/exam';

type ReviewTarget = { lesson: string; anchor: string };

type ErrorBreakdownRow = {
    lesson_id: string;
    lesson_title: string;
    wrong_count: number;
};

type WrongQuestion = {
    id: string;
    question_html: string;
    explanation_html: string | null;
    review: ReviewTarget[];
};

const props = defineProps<{
    track: { slug: string; title_key: string };
    attempt: {
        id: number;
        score_correct: number;
        score_total: number;
        passed: boolean;
    };
    error_breakdown: ErrorBreakdownRow[];
    wrong_questions: WrongQuestion[];
    retry_lessons: string[];
}>();

const percent = Math.round(
    (props.attempt.score_correct / Math.max(1, props.attempt.score_total)) *
        100,
);

function lessonHref(target: ReviewTarget): string {
    return `${showLesson(target.lesson).url}#${target.anchor}`;
}

function retryUrl(): string {
    const params = new URLSearchParams({
        from: props.retry_lessons.join(','),
    });

    return `${startExam({ track: props.track.slug }).url}?${params.toString()}`;
}
</script>

<template>
    <Head :title="trans('Prüfungsergebnis')" />

    <div class="mx-auto max-w-2xl space-y-6 p-4">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <CardTitle>
                        {{
                            trans(':correct von :total richtig — :percent %', {
                                correct: attempt.score_correct,
                                total: attempt.score_total,
                                percent,
                            })
                        }}
                    </CardTitle>
                    <Badge :variant="attempt.passed ? 'default' : 'outline'">
                        {{
                            attempt.passed
                                ? trans('Bestanden')
                                : trans('Nicht bestanden')
                        }}
                    </Badge>
                </div>
            </CardHeader>
        </Card>

        <Card v-if="error_breakdown.length > 0">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Deine Fehler nach Lektion')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <div
                    v-for="row in error_breakdown"
                    :key="row.lesson_id"
                    class="flex items-center justify-between text-sm"
                >
                    <span>{{ row.lesson_title }}</span>
                    <span class="text-muted-foreground">{{
                        trans(':count falsch', { count: row.wrong_count })
                    }}</span>
                </div>
            </CardContent>
        </Card>

        <Card v-for="question in wrong_questions" :key="question.id">
            <CardHeader>
                <CardTitle
                    class="text-sm font-normal"
                    v-html="question.question_html"
                />
            </CardHeader>
            <CardContent class="space-y-2">
                <p
                    v-if="question.explanation_html"
                    class="text-sm"
                    v-html="question.explanation_html"
                />
                <ul class="text-muted-foreground space-y-1 text-sm">
                    <li
                        v-for="target in question.review"
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
            </CardContent>
        </Card>

        <CardDescription v-if="error_breakdown.length === 0">
            {{ trans('Alle übrigen Lektionen vollständig richtig.') }}
        </CardDescription>

        <div class="flex items-center gap-4">
            <Link
                v-if="retry_lessons.length > 0"
                :href="retryUrl()"
                method="post"
                as="button"
            >
                <Button>{{ trans('Gezielt wiederholen') }}</Button>
            </Link>
            <Link :href="showTrack(track.slug)">
                <Button variant="outline">{{
                    trans('Zurück zum Track')
                }}</Button>
            </Link>
        </div>
    </div>
</template>
