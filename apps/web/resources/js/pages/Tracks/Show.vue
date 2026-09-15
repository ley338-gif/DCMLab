<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Circle, CircleDot, Lock } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
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
import { home } from '@/routes';
import { show as showLesson } from '@/routes/lessons';
import { show as showExam, start as startExam } from '@/routes/tracks/exam';
import { show as showTrack } from '@/routes/tracks';

type UnmetRequirement = { lesson_id: string; title: string };

type LessonSummary = {
    lesson_id: string;
    title: string;
    teaser: string;
    duration_minutes: number;
    level: string;
    status: string;
    completed: boolean;
    progress_status: 'started' | 'completed' | null;
    unmet_requires: UnmetRequirement[];
};

type ExamAvailability = {
    available: boolean;
    all_lessons_completed: boolean;
    in_progress_attempt_id: number | null;
    passed: boolean;
};

const props = defineProps<{
    track: { slug: string; title_key: string };
    lessons: LessonSummary[];
    exam: ExamAvailability;
}>();

const page = usePage();
</script>

<template>
    <Head :title="trans(track.title_key)" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: home() },
                { title: trans(track.title_key), href: showTrack(track.slug) },
            ]"
        />

        <h1 class="mb-8 text-2xl font-semibold">
            {{ trans(track.title_key) }}
        </h1>

        <ul class="flex flex-col gap-3">
            <li v-for="lesson in lessons" :key="lesson.lesson_id">
                <component
                    :is="page.props.auth.user ? Link : 'div'"
                    :href="
                        page.props.auth.user
                            ? showLesson(lesson.lesson_id)
                            : undefined
                    "
                    class="block"
                >
                    <Card
                        class="transition-colors"
                        :class="{
                            'hover:bg-accent/50': page.props.auth.user,
                        }"
                    >
                        <CardHeader>
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <CardTitle class="flex items-center gap-2">
                                    <template v-if="page.props.auth.user">
                                        <CheckCircle2
                                            v-if="
                                                lesson.progress_status ===
                                                'completed'
                                            "
                                            class="size-4 text-green-600"
                                        />
                                        <CircleDot
                                            v-else-if="
                                                lesson.progress_status ===
                                                'started'
                                            "
                                            class="text-muted-foreground size-4"
                                        />
                                        <Circle
                                            v-else
                                            class="text-muted-foreground size-4"
                                        />
                                    </template>
                                    {{ lesson.lesson_id }} —
                                    {{ lesson.title }}
                                </CardTitle>
                                <Badge variant="outline">
                                    {{
                                        trans(':minutes Min.', {
                                            minutes: lesson.duration_minutes,
                                        })
                                    }}
                                </Badge>
                            </div>
                            <CardDescription>{{
                                lesson.teaser
                            }}</CardDescription>
                            <p
                                v-if="lesson.unmet_requires.length"
                                class="text-muted-foreground flex items-center gap-1.5 text-xs"
                            >
                                <Lock class="size-3.5 shrink-0" />
                                {{ trans('Setzt voraus') }}:
                                {{
                                    lesson.unmet_requires
                                        .map((r) => r.title)
                                        .join(', ')
                                }}
                            </p>
                        </CardHeader>
                    </Card>
                </component>
            </li>
        </ul>

        <p
            v-if="!page.props.auth.user"
            class="text-muted-foreground mt-6 text-sm"
        >
            {{
                trans(
                    'Melde dich an, um Lektionen zu lesen und deinen Fortschritt zu speichern.',
                )
            }}
        </p>

        <Card v-if="page.props.auth.user && exam.available" class="mt-6">
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <CardTitle class="flex items-center gap-2">
                        <CheckCircle2
                            v-if="exam.passed"
                            class="size-4 text-green-600"
                        />
                        {{ trans('Abschlussprüfung') }}
                    </CardTitle>
                    <Badge v-if="exam.passed" variant="default">{{
                        trans('Bestanden')
                    }}</Badge>
                </div>
                <CardDescription v-if="!exam.all_lessons_completed">
                    {{
                        trans(
                            'Du hast noch nicht alle Lektionen dieses Tracks gelesen. Du kannst trotzdem antreten.',
                        )
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Link
                    v-if="exam.in_progress_attempt_id"
                    :href="
                        showExam({
                            track: props.track.slug,
                            attempt: exam.in_progress_attempt_id,
                        })
                    "
                >
                    <Button size="sm">{{ trans('Prüfung fortsetzen') }}</Button>
                </Link>
                <Link
                    v-else
                    :href="startExam({ track: props.track.slug })"
                    method="post"
                    as="button"
                >
                    <Button size="sm">{{ trans('Prüfung starten') }}</Button>
                </Link>
            </CardContent>
        </Card>
    </PageContainer>
</template>
