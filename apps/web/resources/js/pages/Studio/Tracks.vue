<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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
    publish as publishTrack,
    restore as restoreTrack,
    store as storeTrack,
    unpublish as unpublishTrack,
    update as updateTrack,
} from '@/routes/studio/tracks';

type Status = 'draft' | 'published' | 'archived';
type Level = 'einsteiger' | 'aufbau' | 'fortgeschritten';

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
};

type Themenfeld = { id: number; slug: string };

const props = defineProps<{
    tracks: TrackRow[];
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
            </Card>
        </div>
    </PageContainer>
</template>
