<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from '@/lib/trans';
import { index as studioIndex } from '@/routes/studio';
import {
    archive as archiveTrack,
    moveLesson,
    publish as publishTrack,
    reorderLessons,
    restore as restoreTrack,
    store as storeTrack,
    unpublish as unpublishTrack,
    update as updateTrack,
} from '@/routes/studio/tracks';

type Status = 'draft' | 'published' | 'archived';
type Level = 'einsteiger' | 'aufbau' | 'fortgeschritten';

type TrackLessonRow = { lesson_id: string; title: string; order: number };

type TrackRow = {
    slug: string;
    title: string;
    teaser: string | null;
    level: Level;
    hours: number;
    order: number;
    status: Status;
    themenfeld_id: number;
    lessons_count: number;
    lessons: TrackLessonRow[];
};

type Themenfeld = { id: number; slug: string };
type AllLessonRow = {
    lesson_id: string;
    title: string;
    track_slug: string | null;
};

const props = defineProps<{
    tracks: TrackRow[];
    all_lessons: AllLessonRow[];
    themenfelder: Themenfeld[];
    can_manage: boolean;
}>();

const statusLabels: Record<Status, string> = {
    draft: trans('Entwurf'),
    published: trans('Veröffentlicht'),
    archived: trans('Archiviert'),
};

const levelLabels: Record<Level, string> = {
    einsteiger: trans('Einsteiger'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};

type EditableFields = {
    title: string;
    teaser: string;
    themenfeld_id: number;
    level: Level;
    hours: number;
    order: number;
};

const editing = ref<string | null>(null);
const editForm = ref<EditableFields>({
    title: '',
    teaser: '',
    themenfeld_id: props.themenfelder[0]?.id ?? 0,
    level: 'einsteiger',
    hours: 1,
    order: 0,
});

const newTrack = ref<{ slug: string } & EditableFields>({
    slug: '',
    title: '',
    teaser: '',
    themenfeld_id: props.themenfelder[0]?.id ?? 0,
    level: 'einsteiger',
    hours: 1,
    order: 0,
});

function startEditing(track: TrackRow) {
    editing.value = track.slug;
    editForm.value = {
        title: track.title,
        teaser: track.teaser ?? '',
        themenfeld_id: track.themenfeld_id,
        level: track.level,
        hours: track.hours,
        order: track.order,
    };
}

function saveTrack(slug: string) {
    router.patch(updateTrack.url({ track: slug }), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function createTrack() {
    if (
        newTrack.value.slug.trim() === '' ||
        newTrack.value.title.trim() === ''
    ) {
        return;
    }

    router.post(storeTrack.url(), newTrack.value, {
        preserveScroll: true,
        onSuccess: () => {
            newTrack.value = {
                slug: '',
                title: '',
                teaser: '',
                themenfeld_id: props.themenfelder[0]?.id ?? 0,
                level: 'einsteiger',
                hours: 1,
                order: 0,
            };
        },
    });
}

function transition(
    track: TrackRow,
    action: 'publish' | 'unpublish' | 'archive' | 'restore',
) {
    const routes = {
        publish: publishTrack,
        unpublish: unpublishTrack,
        archive: archiveTrack,
        restore: restoreTrack,
    };

    router.post(
        routes[action].url({ track: track.slug }),
        {},
        { preserveScroll: true },
    );
}

// Lektionen dieser Track (Studio-Lessons-Umbau): nur eine Track gleichzeitig
// offen, analog zu `editing` oben -- lokale Kopie der Reihenfolge fuer das
// Drag & Drop, derselbe Aufbau wie in Studio/Lessons/Elements.vue fuer
// lesson_elements, hier eine Ebene hoeher (Lesson statt Element).
const managingLessons = ref<string | null>(null);
const lessonOrder = ref<TrackLessonRow[]>([]);
const draggedLessonIndex = ref<number | null>(null);
const moveTargetLessonId = ref('');

function toggleLessonManagement(track: TrackRow) {
    if (managingLessons.value === track.slug) {
        managingLessons.value = null;
        return;
    }

    managingLessons.value = track.slug;
    lessonOrder.value = [...track.lessons].sort((a, b) => a.order - b.order);
    moveTargetLessonId.value = '';
}

function lessonsAvailableToMove(track: TrackRow) {
    return props.all_lessons.filter(
        (lesson) => lesson.track_slug !== track.slug,
    );
}

function onLessonDragStart(index: number) {
    draggedLessonIndex.value = index;
}

function onLessonDrop(track: TrackRow, targetIndex: number) {
    const fromIndex = draggedLessonIndex.value;
    draggedLessonIndex.value = null;

    if (fromIndex === null || fromIndex === targetIndex) {
        return;
    }

    const reordered = [...lessonOrder.value];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(targetIndex, 0, moved);
    lessonOrder.value = reordered;

    router.patch(
        reorderLessons.url({ track: track.slug }),
        { order: reordered.map((lesson) => lesson.lesson_id) },
        { preserveScroll: true, preserveState: true },
    );
}

function moveLessonHere(track: TrackRow) {
    if (moveTargetLessonId.value === '') {
        return;
    }

    router.post(
        moveLesson.url({ track: track.slug }),
        { lesson_id: moveTargetLessonId.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                moveTargetLessonId.value = '';
            },
        },
    );
}

// `lessonOrder` ist eine lokale Kopie (s.o.), keine berechnete Ansicht auf
// `props.tracks` -- ohne diesen Watcher wuerde eine gerade verschobene
// Lektion zwar serverseitig sofort in ihrer neuen Track stehen, aber erst
// nach einem manuellen Reload in diesem Panel auftauchen (derselbe Grund
// wie der Watcher in Studio/Lessons/Elements.vue fuer `items`).
watch(
    () => props.tracks,
    (tracks) => {
        if (managingLessons.value === null) {
            return;
        }

        const track = tracks.find((t) => t.slug === managingLessons.value);

        if (track) {
            lessonOrder.value = [...track.lessons].sort(
                (a, b) => a.order - b.order,
            );
        }
    },
);
</script>

<template>
    <Head :title="trans('Tracks')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Tracks'), href: '' },
            ]"
        />

        <h1 class="mb-2 text-2xl font-semibold">{{ trans('Tracks') }}</h1>
        <p class="text-muted-foreground mb-6 text-sm">
            {{
                trans(
                    'Archivierte Tracks sind für Lernende nicht mehr sichtbar, bleiben aber erhalten.',
                )
            }}
        </p>

        <Card v-if="props.can_manage" class="mb-6">
            <CardHeader>
                <CardTitle>{{ trans('Neuen Track anlegen') }}</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-1.5">
                    <Label>{{ trans('Slug') }}</Label>
                    <Input
                        v-model="newTrack.slug"
                        :placeholder="trans('z. B. netzwerksicherheit')"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Titel') }}</Label>
                    <Input v-model="newTrack.title" />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Teaser') }}</Label>
                    <Input v-model="newTrack.teaser" />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Themenfeld') }}</Label>
                    <select
                        v-model.number="newTrack.themenfeld_id"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option
                            v-for="themenfeld in props.themenfelder"
                            :key="themenfeld.id"
                            :value="themenfeld.id"
                        >
                            {{ themenfeld.slug }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Niveau') }}</Label>
                    <select
                        v-model="newTrack.level"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option
                            v-for="(label, level) in levelLabels"
                            :key="level"
                            :value="level"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Stunden') }}</Label>
                    <Input
                        v-model.number="newTrack.hours"
                        type="number"
                        min="0"
                    />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <Button @click="createTrack">{{ trans('Anlegen') }}</Button>
                </div>
            </CardContent>
        </Card>

        <div class="flex flex-col gap-3">
            <Card v-for="track in props.tracks" :key="track.slug">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{
                            track.title
                        }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ track.slug }} · {{ levelLabels[track.level] }} ·
                            {{
                                trans(':count Lektionen', {
                                    count: track.lessons_count,
                                })
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                track.status === 'published'
                                    ? 'default'
                                    : track.status === 'archived'
                                      ? 'secondary'
                                      : 'outline'
                            "
                        >
                            {{ statusLabels[track.status] }}
                        </Badge>
                        <template v-if="props.can_manage">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="
                                    editing === track.slug
                                        ? (editing = null)
                                        : startEditing(track)
                                "
                            >
                                {{
                                    editing === track.slug
                                        ? trans('Schließen')
                                        : trans('Bearbeiten')
                                }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="toggleLessonManagement(track)"
                            >
                                {{
                                    managingLessons === track.slug
                                        ? trans('Schließen')
                                        : trans('Lektionen verwalten')
                                }}
                            </Button>
                            <Button
                                v-if="track.status === 'draft'"
                                size="sm"
                                @click="transition(track, 'publish')"
                            >
                                {{ trans('Veröffentlichen') }}
                            </Button>
                            <Button
                                v-if="track.status === 'published'"
                                variant="outline"
                                size="sm"
                                @click="transition(track, 'unpublish')"
                            >
                                {{ trans('Zurücknehmen') }}
                            </Button>
                            <Button
                                v-if="track.status !== 'archived'"
                                variant="ghost"
                                size="sm"
                                @click="transition(track, 'archive')"
                            >
                                {{ trans('Archivieren') }}
                            </Button>
                            <Button
                                v-if="track.status === 'archived'"
                                variant="outline"
                                size="sm"
                                @click="transition(track, 'restore')"
                            >
                                {{ trans('Wiederherstellen') }}
                            </Button>
                        </template>
                    </div>
                </CardHeader>
                <CardContent
                    v-if="editing === track.slug"
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="space-y-1.5">
                        <Label>{{ trans('Titel') }}</Label>
                        <Input v-model="editForm.title" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Teaser') }}</Label>
                        <Input v-model="editForm.teaser" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Themenfeld') }}</Label>
                        <select
                            v-model.number="editForm.themenfeld_id"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option
                                v-for="themenfeld in props.themenfelder"
                                :key="themenfeld.id"
                                :value="themenfeld.id"
                            >
                                {{ themenfeld.slug }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Niveau') }}</Label>
                        <select
                            v-model="editForm.level"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option
                                v-for="(label, level) in levelLabels"
                                :key="level"
                                :value="level"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Stunden') }}</Label>
                        <Input
                            v-model.number="editForm.hours"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Reihenfolge') }}</Label>
                        <Input
                            v-model.number="editForm.order"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <Button @click="saveTrack(track.slug)">{{
                            trans('Speichern')
                        }}</Button>
                    </div>
                </CardContent>
                <CardContent v-else-if="track.teaser">
                    <p class="text-muted-foreground text-sm">
                        {{ track.teaser }}
                    </p>
                </CardContent>

                <CardContent v-if="managingLessons === track.slug">
                    <p class="text-muted-foreground mb-3 text-sm">
                        {{
                            trans(
                                'Ziehe die Lektionen, um ihre Reihenfolge innerhalb dieser Track zu ändern.',
                            )
                        }}
                    </p>
                    <div class="mb-4 flex flex-col gap-2">
                        <Card
                            v-for="(lesson, index) in lessonOrder"
                            :key="lesson.lesson_id"
                            draggable="true"
                            class="cursor-grab"
                            @dragstart="onLessonDragStart(index)"
                            @dragover.prevent
                            @drop="onLessonDrop(track, index)"
                        >
                            <CardHeader
                                class="flex flex-row items-center gap-3 py-3"
                            >
                                <Badge variant="outline">{{ index + 1 }}</Badge>
                                <CardTitle class="flex-1 text-sm">{{
                                    lesson.title
                                }}</CardTitle>
                                <span class="text-muted-foreground text-xs">{{
                                    lesson.lesson_id
                                }}</span>
                            </CardHeader>
                        </Card>
                        <p
                            v-if="lessonOrder.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            {{ trans('Noch keine Lektionen in dieser Track.') }}
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-end"
                    >
                        <div class="flex-1 space-y-1.5">
                            <Label>{{
                                trans('Lektion hierher verschieben')
                            }}</Label>
                            <select
                                v-model="moveTargetLessonId"
                                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="">
                                    {{ trans('-- Lektion auswählen --') }}
                                </option>
                                <option
                                    v-for="lesson in lessonsAvailableToMove(
                                        track,
                                    )"
                                    :key="lesson.lesson_id"
                                    :value="lesson.lesson_id"
                                >
                                    {{ lesson.title }} ({{ lesson.lesson_id }})
                                </option>
                            </select>
                        </div>
                        <Button
                            :disabled="moveTargetLessonId === ''"
                            @click="moveLessonHere(track)"
                        >
                            {{ trans('Verschieben') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
