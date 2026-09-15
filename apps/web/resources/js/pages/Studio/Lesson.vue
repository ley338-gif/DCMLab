<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { index as studioIndex } from '@/routes/studio';

type ElementRow = {
    id: number;
    position: number;
    kind: string;
    label: string;
};

const props = defineProps<{
    lesson: { lesson_id: string; title: string };
    elements: ElementRow[];
}>();

const kindLabels: Record<string, string> = {
    content: trans('Lektionstext'),
    sandbox: trans('Sandbox'),
    node: trans('Lab / Node'),
    quiz: trans('Quiz'),
};
</script>

<template>
    <Head
        :title="trans('Elementreihenfolge: :title', { title: lesson.title })"
    />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: lesson.title, href: '' },
            ]"
        />

        <h1 class="mb-2 text-2xl font-semibold">
            {{ trans('Elementreihenfolge') }}
        </h1>
        <p class="text-muted-foreground mb-6 text-sm">
            {{
                trans(
                    'So ist :title aktuell aufgebaut — in dieser Reihenfolge sehen Lernende die Lektion. Umsortieren per Drag & Drop folgt.',
                    { title: lesson.title },
                )
            }}
        </p>

        <div class="flex flex-col gap-2">
            <Card v-for="(element, index) in props.elements" :key="element.id">
                <CardHeader class="flex flex-row items-center gap-3 py-4">
                    <Badge variant="outline">{{ index + 1 }}</Badge>
                    <CardTitle class="flex-1 text-base">{{
                        element.label
                    }}</CardTitle>
                    <Badge variant="secondary">{{
                        kindLabels[element.kind] ?? element.kind
                    }}</Badge>
                </CardHeader>
            </Card>

            <p
                v-if="props.elements.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Noch keine Elementsequenz vorhanden.') }}
            </p>
        </div>
    </PageContainer>
</template>
