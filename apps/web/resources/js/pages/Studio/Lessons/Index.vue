<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { trans } from '@/lib/trans';
import { index as studioIndex } from '@/routes/studio';
import {
    edit as editLesson,
    preview as previewLesson,
} from '@/routes/studio/lessons';
import { show as showLessonElements } from '@/routes/studio/lessons/elements';

type Level = 'einsteiger' | 'aufbau' | 'fortgeschritten';
type PendingVersionStatus = 'draft' | 'review' | null;

type LessonRow = {
    lesson_id: string;
    title: string;
    track: { slug: string; title: string } | null;
    level: Level;
    status: string;
    pending_version_status: PendingVersionStatus;
};

type Track = { slug: string; title: string };

const props = defineProps<{
    lessons: LessonRow[];
    tracks: Track[];
}>();

const levelLabels: Record<Level, string> = {
    einsteiger: trans('Einsteiger'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};

const pendingVersionLabels: Record<'draft' | 'review', string> = {
    draft: trans('Entwurf offen'),
    review: trans('Zur Prüfung eingereicht'),
};

const search = ref('');
const trackFilter = ref('');
const levelFilter = ref<Level | ''>('');
const statusFilter = ref('');

const statuses = computed(() =>
    [...new Set(props.lessons.map((lesson) => lesson.status))].sort(),
);

const filteredLessons = computed(() =>
    props.lessons.filter((lesson) => {
        if (
            search.value.trim() !== '' &&
            !lesson.title
                .toLowerCase()
                .includes(search.value.trim().toLowerCase()) &&
            !lesson.lesson_id
                .toLowerCase()
                .includes(search.value.trim().toLowerCase())
        ) {
            return false;
        }

        if (
            trackFilter.value !== '' &&
            lesson.track?.slug !== trackFilter.value
        ) {
            return false;
        }

        if (levelFilter.value !== '' && lesson.level !== levelFilter.value) {
            return false;
        }

        if (statusFilter.value !== '' && lesson.status !== statusFilter.value) {
            return false;
        }

        return true;
    }),
);
</script>

<template>
    <Head :title="trans('Lessons')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Lessons'), href: '' },
            ]"
        />

        <div class="mb-6">
            <h1 class="text-2xl font-semibold">{{ trans('Lessons') }}</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                {{
                    trans(
                        'Lektionsinhalte entstehen weiterhin über content:sync — hier werden bestehende Lektionen bearbeitet, in Vorschau angesehen und ihre Elementreihenfolge verwaltet.',
                    )
                }}
            </p>
        </div>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Input
                v-model="search"
                :placeholder="trans('Suche nach Titel oder Lektions-ID…')"
            />
            <select
                v-model="trackFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Tracks') }}</option>
                <option
                    v-for="track in props.tracks"
                    :key="track.slug"
                    :value="track.slug"
                >
                    {{ track.title }}
                </option>
            </select>
            <select
                v-model="levelFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Niveaus') }}</option>
                <option
                    v-for="(label, level) in levelLabels"
                    :key="level"
                    :value="level"
                >
                    {{ label }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Status') }}</option>
                <option
                    v-for="status in statuses"
                    :key="status"
                    :value="status"
                >
                    {{ status }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-3">
            <Card v-for="lesson in filteredLessons" :key="lesson.lesson_id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{
                            lesson.title
                        }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ lesson.lesson_id }} ·
                            {{ lesson.track?.title ?? trans('ohne Track') }} ·
                            {{ levelLabels[lesson.level] }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            v-if="lesson.pending_version_status"
                            variant="outline"
                        >
                            {{
                                pendingVersionLabels[
                                    lesson.pending_version_status
                                ]
                            }}
                        </Badge>
                        <Badge variant="secondary">{{ lesson.status }}</Badge>
                        <Link
                            :href="editLesson.url({ lesson: lesson.lesson_id })"
                        >
                            <Button variant="outline" size="sm">{{
                                trans('Bearbeiten')
                            }}</Button>
                        </Link>
                        <a
                            :href="
                                previewLesson.url({ lesson: lesson.lesson_id })
                            "
                            target="_blank"
                            rel="noopener"
                        >
                            <Button variant="ghost" size="sm">{{
                                trans('Vorschau')
                            }}</Button>
                        </a>
                        <Link
                            :href="
                                showLessonElements.url({
                                    lesson: lesson.lesson_id,
                                })
                            "
                        >
                            <Button variant="ghost" size="sm">{{
                                trans('Elemente')
                            }}</Button>
                        </Link>
                    </div>
                </CardHeader>
            </Card>

            <p
                v-if="filteredLessons.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Keine Lektionen gefunden.') }}
            </p>
        </div>
    </PageContainer>
</template>
