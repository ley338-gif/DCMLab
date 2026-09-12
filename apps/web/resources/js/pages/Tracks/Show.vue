<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { dashboard, home, login, register } from '@/routes';
import { show as showLesson } from '@/routes/lessons';

type LessonSummary = {
    lesson_id: string;
    title: string;
    teaser: string;
    duration_minutes: number;
    level: string;
    status: string;
    completed: boolean;
};

defineProps<{
    track: { slug: string; title_key: string };
    lessons: LessonSummary[];
}>();

const page = usePage();
</script>

<template>
    <Head :title="trans(track.title_key)" />

    <div class="bg-background min-h-screen">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4"
            >
                <Link :href="home()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-4 text-sm">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboard()">{{
                            trans('Dashboard')
                        }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">{{ trans('Log in') }}</Link>
                        <Link
                            :href="register()"
                            class="text-primary font-medium"
                            >{{ trans('Register') }}</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <Link
                :href="home()"
                class="text-muted-foreground mb-4 inline-block text-sm"
                >&larr; {{ trans('Tracks') }}</Link
            >
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
                                        <CheckCircle2
                                            v-if="lesson.completed"
                                            class="size-4 text-green-600"
                                        />
                                        {{ lesson.lesson_id }} —
                                        {{ lesson.title }}
                                    </CardTitle>
                                    <Badge variant="outline">
                                        {{
                                            trans(':minutes Min.', {
                                                minutes:
                                                    lesson.duration_minutes,
                                            })
                                        }}
                                    </Badge>
                                </div>
                                <CardDescription>{{
                                    lesson.teaser
                                }}</CardDescription>
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
        </main>
    </div>
</template>
