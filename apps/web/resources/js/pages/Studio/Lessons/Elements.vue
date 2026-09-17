<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { GripVertical } from '@lucide/vue';
import { ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { index as studioIndex } from '@/routes/studio';
import { attachLab, reorder } from '@/routes/studio/lessons/elements';

type ElementRow = {
    id: number;
    position: number;
    kind: string;
    label: string;
};

type AvailableLab = {
    slug: string;
    title: string;
};

const props = defineProps<{
    lesson: { lesson_id: string; title: string };
    elements: ElementRow[];
    available_labs: AvailableLab[];
    can_manage: boolean;
}>();

const kindLabels: Record<string, string> = {
    content: trans('Lektionstext'),
    sandbox: trans('Sandbox'),
    node: trans('Lab / Node'),
    lab: trans('Lab'),
    quiz: trans('Quiz'),
};

const selectedLabSlug = ref('');

function attachSelectedLab() {
    if (selectedLabSlug.value === '') {
        return;
    }

    router.post(
        attachLab.url({ lesson: props.lesson.lesson_id }),
        { lab_slug: selectedLabSlug.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedLabSlug.value = '';
            },
        },
    );
}

// Optimistisch lokal umsortiert, dann per PATCH gespeichert (CMS-6c) --
// noch schlichtes natives HTML5-Drag&Drop statt einer neuen Abhaengigkeit,
// fuer eine einzelne vertikale Liste reicht das.
const items = ref<ElementRow[]>([...props.elements]);
const draggedIndex = ref<number | null>(null);

// `items` ist eine lokale Kopie (s.o.), keine berechnete Ansicht auf
// `props.elements` -- ohne diesen Watcher wuerde ein frisch angehaengtes
// Lab zwar serverseitig existieren (sichtbar an der aktualisierten
// `available_labs`-Liste), aber erst nach einem manuellen Reload in dieser
// Liste auftauchen.
watch(
    () => props.elements,
    (elements) => {
        items.value = [...elements];
    },
);

function onDragStart(index: number) {
    draggedIndex.value = index;
}

function onDrop(targetIndex: number) {
    const fromIndex = draggedIndex.value;
    draggedIndex.value = null;

    if (fromIndex === null || fromIndex === targetIndex) {
        return;
    }

    const reordered = [...items.value];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(targetIndex, 0, moved);
    items.value = reordered;

    router.patch(
        reorder.url({ lesson: props.lesson.lesson_id }),
        { order: reordered.map((item) => item.id) },
        { preserveScroll: true, preserveState: true },
    );
}
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
                can_manage
                    ? trans(
                          'So ist :title aktuell aufgebaut — ziehe die Karten, um die Reihenfolge zu ändern.',
                          { title: lesson.title },
                      )
                    : trans('So ist :title aktuell aufgebaut.', {
                          title: lesson.title,
                      })
            }}
        </p>

        <div class="flex flex-col gap-2">
            <Card
                v-for="(element, index) in items"
                :key="element.id"
                :draggable="can_manage"
                class="transition-opacity"
                :class="{ 'cursor-grab': can_manage }"
                @dragstart="onDragStart(index)"
                @dragover.prevent
                @drop="onDrop(index)"
            >
                <CardHeader class="flex flex-row items-center gap-3 py-4">
                    <GripVertical
                        v-if="can_manage"
                        class="text-muted-foreground size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <Badge variant="outline">{{ index + 1 }}</Badge>
                    <CardTitle class="flex-1 text-base">{{
                        element.label
                    }}</CardTitle>
                    <Badge variant="secondary">{{
                        kindLabels[element.kind] ?? element.kind
                    }}</Badge>
                </CardHeader>
            </Card>

            <p v-if="items.length === 0" class="text-muted-foreground text-sm">
                {{ trans('Noch keine Elementsequenz vorhanden.') }}
            </p>
        </div>

        <Card v-if="can_manage" class="mt-6">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Lab hinzufügen')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1 space-y-1.5">
                    <select
                        v-model="selectedLabSlug"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option value="">
                            {{ trans('-- Lab auswählen --') }}
                        </option>
                        <option
                            v-for="lab in available_labs"
                            :key="lab.slug"
                            :value="lab.slug"
                        >
                            {{ lab.title }}
                        </option>
                    </select>
                    <p
                        v-if="available_labs.length === 0"
                        class="text-muted-foreground text-xs"
                    >
                        {{
                            trans(
                                'Keine veröffentlichten Labs verfügbar, die dieser Lektion noch nicht angehängt sind.',
                            )
                        }}
                    </p>
                </div>
                <Button
                    :disabled="selectedLabSlug === ''"
                    @click="attachSelectedLab"
                >
                    {{ trans('Hinzufügen') }}
                </Button>
            </CardContent>
        </Card>
    </PageContainer>
</template>
