<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck, FlaskConical } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { complete, reopen } from '@/routes/lessons';

type ToolbarTool = {
    slug: string;
    name: string;
    purpose: string | null;
    example: string | null;
    is_new: boolean;
};

type ToolbarData = {
    tools: ToolbarTool[];
    needs_sandbox: boolean;
    dataset: { note: string | null; file_count: number | null } | null;
    requires: { lesson_id: string; title: string }[];
    lab_node: {
        slug: string;
        title: string;
        difficulty: string;
        points: number;
    } | null;
    lab_optional: boolean;
};

const props = defineProps<{
    lesson: {
        lesson_id: string;
        title: string;
        teaser: string;
        objectives: string[];
        duration_minutes: number;
        body_html: string;
    };
    toolbar: ToolbarData;
    progress: { status: string; is_returning_visit: boolean };
}>();

// Abschnitt 4.4: "Einklappbar, beim zweiten Besuch einer Lektion
// standardmaessig zu."
const toolbarOpen = ref(!props.progress.is_returning_visit);
</script>

<template>
    <Head :title="lesson.title" />

    <div class="bg-background min-h-screen">
        <main class="mx-auto max-w-3xl px-6 py-10">
            <Card class="mb-6">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-6 py-4 text-left"
                    @click="toolbarOpen = !toolbarOpen"
                >
                    <span
                        class="text-sm font-semibold tracking-wide uppercase"
                        >{{ trans('Für diese Lektion') }}</span
                    >
                    <Badge variant="outline"
                        >{{
                            trans(':minutes Min.', {
                                minutes: lesson.duration_minutes,
                            })
                        }}
                    </Badge>
                </button>

                <CardContent v-if="toolbarOpen" class="space-y-4 pt-0">
                    <div v-if="toolbar.tools.length" class="space-y-2">
                        <p
                            class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                        >
                            {{ trans('Werkzeuge') }}
                        </p>
                        <div
                            v-for="tool in toolbar.tools"
                            :key="tool.slug"
                            class="text-sm"
                        >
                            <div class="flex items-center gap-2">
                                <code class="font-semibold">{{
                                    tool.name
                                }}</code>
                                <span class="text-muted-foreground">{{
                                    tool.purpose
                                }}</span>
                                <Badge v-if="tool.is_new" class="text-xs"
                                    >{{ trans('NEU') }}
                                </Badge>
                            </div>
                            <code
                                v-if="tool.example"
                                class="text-muted-foreground block pl-0 text-xs"
                                >{{ tool.example }}</code
                            >
                        </div>
                    </div>

                    <div v-if="toolbar.dataset" class="text-sm">
                        <span
                            class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                            >{{ trans('Liegt bereit') }}</span
                        >
                        <span class="ml-2">
                            {{ toolbar.dataset.note }}
                            <template v-if="toolbar.dataset.file_count">
                                ·
                                {{
                                    trans(':count Dateien, synthetisch', {
                                        count: toolbar.dataset.file_count,
                                    })
                                }}
                            </template>
                        </span>
                    </div>

                    <div v-if="toolbar.requires.length" class="text-sm">
                        <span
                            class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                            >{{ trans('Vorher') }}</span
                        >
                        <span class="ml-2">
                            <template
                                v-for="(req, index) in toolbar.requires"
                                :key="req.lesson_id"
                            >
                                <span v-if="index > 0">, </span>
                                {{ req.lesson_id }} — {{ req.title }}
                            </template>
                        </span>
                    </div>

                    <div v-if="toolbar.lab_node" class="text-sm">
                        <span
                            class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                            >{{ trans('Danach') }}</span
                        >
                        <span class="ml-2">
                            {{
                                trans(
                                    'Lab: Node „:title" (:difficulty, :points Pkt.)',
                                    {
                                        title: toolbar.lab_node.title,
                                        difficulty: toolbar.lab_node.difficulty,
                                        points: toolbar.lab_node.points,
                                    },
                                )
                            }}
                        </span>
                    </div>

                    <Button
                        v-if="toolbar.needs_sandbox"
                        disabled
                        variant="outline"
                        class="w-full"
                    >
                        <FlaskConical class="size-4" />
                        {{ trans('Spielwiese starten') }}
                        <span class="text-muted-foreground text-xs"
                            >({{
                                trans('folgt in einer späteren Phase')
                            }})</span
                        >
                    </Button>
                </CardContent>
            </Card>

            <h1 class="mb-1 text-2xl font-semibold">{{ lesson.title }}</h1>
            <p class="text-muted-foreground mb-6">{{ lesson.teaser }}</p>

            <Card v-if="lesson.objectives.length" class="mb-8">
                <CardHeader>
                    <CardTitle class="text-sm"
                        >{{ trans('Lernziele') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="list-inside list-disc space-y-1 text-sm">
                        <li
                            v-for="(objective, i) in lesson.objectives"
                            :key="i"
                        >
                            {{ objective }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <div class="lesson-prose" v-html="lesson.body_html" />

            <div class="mt-10 flex items-center gap-3 border-t pt-6">
                <Form
                    v-if="progress.status !== 'completed'"
                    v-bind="complete.form(lesson.lesson_id)"
                    v-slot="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        <CircleCheck class="size-4" />
                        {{ trans('Als erledigt markieren') }}
                    </Button>
                </Form>
                <Form
                    v-else
                    v-bind="reopen.form(lesson.lesson_id)"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="processing"
                    >
                        {{ trans('Als offen markieren') }}
                    </Button>
                </Form>
                <span
                    v-if="progress.status === 'completed'"
                    class="text-sm text-green-600"
                    >{{ trans('Erledigt') }}</span
                >
            </div>
        </main>
    </div>
</template>

<style>
.lesson-prose h2 {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
}
.lesson-prose p,
.lesson-prose ul,
.lesson-prose ol,
.lesson-prose table,
.lesson-prose pre,
.lesson-prose blockquote,
.lesson-prose details {
    margin-bottom: 1rem;
}
.lesson-prose ul,
.lesson-prose ol {
    padding-left: 1.5rem;
}
.lesson-prose ul {
    list-style: disc;
}
.lesson-prose ol {
    list-style: decimal;
}
.lesson-prose pre {
    overflow-x: auto;
    border-radius: 0.5rem;
    background: var(--muted);
    padding: 1rem;
    font-size: 0.8125rem;
}
.lesson-prose table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.lesson-prose th,
.lesson-prose td {
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    text-align: left;
}
.lesson-prose blockquote {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    color: var(--muted-foreground);
}
.lesson-prose .glossary-term {
    text-decoration: underline dotted;
    cursor: help;
}
.lesson-prose details {
    border: 1px solid var(--border);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
}
.lesson-prose summary {
    cursor: pointer;
    font-weight: 500;
}
</style>
