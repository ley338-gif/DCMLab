<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { edit, store, validate } from '@/routes/author/exams/edit';
import { publish, submit } from '@/routes/author/quiz-versions';
import { show as showTrack } from '@/routes/tracks';

type ExamFields = {
    title: string;
    intro: string;
    pass_percent: number;
    draw: number;
    duration_minutes: number;
    shuffle: boolean;
    min_per_lesson: number;
};

type TypeShare = { type: string; percent: number; min: number; max: number };

type Coverage = {
    pool_size: number;
    min_per_lesson: number;
    lessons: { lesson_id: string; count: number; ok: boolean }[];
    cross_count: number;
    cross_ok: boolean;
    type_shares: TypeShare[];
    difficulty3_share: number;
};

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
} | null;

const props = defineProps<{
    track: { slug: string; title_key: string };
    fields: ExamFields;
    coverage: Coverage;
    pending_version: PendingVersion;
    can_publish: boolean;
}>();

const fields = ref<ExamFields>({ ...props.fields });
const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validate.url({ track: props.track.slug }),
            fields.value,
        );
        issues.value = result.issues;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    router.post(store.url({ track: props.track.slug }), fields.value, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        onSuccess: () => runValidation(),
    });
}

function submitForReview() {
    if (props.pending_version === null) {
        return;
    }

    acting.value = true;
    router.post(
        submit.url({ version: props.pending_version.id }),
        {},
        { preserveScroll: true, onFinish: () => (acting.value = false) },
    );
}

function publishVersion() {
    if (props.pending_version === null) {
        return;
    }

    acting.value = true;
    router.post(
        publish.url({ version: props.pending_version.id }),
        {},
        { preserveScroll: true, onFinish: () => (acting.value = false) },
    );
}

const statusLabels: Record<string, string> = {
    draft: trans('Entwurf'),
    review: trans('Zur Prüfung eingereicht'),
    published: trans('Veröffentlicht'),
};
</script>

<template>
    <Head :title="trans('Prüfung bearbeiten: :track', { track: track.slug })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: home() },
                { title: track.slug, href: showTrack(track.slug) },
                { title: trans('Prüfung bearbeiten'), href: edit(track.slug) },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{ trans('Prüfung bearbeiten: :track', { track: track.slug }) }}
            </h1>
            <Badge v-if="pending_version" variant="outline">
                {{ statusLabels[pending_version.status] }}
            </Badge>
        </div>

        <Alert v-if="issues.length > 0" variant="destructive" class="mb-6">
            <AlertTitle>{{ trans('Befunde') }}</AlertTitle>
            <AlertDescription>
                <ul class="list-inside list-disc">
                    <li v-for="(issue, index) in issues" :key="index">
                        {{ issue }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Einstellungen')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="title">{{ trans('Titel') }}</Label>
                        <Input id="title" v-model="fields.title" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="intro">{{ trans('Einleitung') }}</Label>
                        <Input id="intro" v-model="fields.intro" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="pass_percent">{{
                            trans('Bestehensgrenze (%)')
                        }}</Label>
                        <Input
                            id="pass_percent"
                            v-model.number="fields.pass_percent"
                            type="number"
                            min="50"
                            max="100"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="draw">{{
                            trans('Fragenzahl je Versuch')
                        }}</Label>
                        <Input
                            id="draw"
                            v-model.number="fields.draw"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="duration_minutes">{{
                            trans('Dauer (Minuten)')
                        }}</Label>
                        <Input
                            id="duration_minutes"
                            v-model.number="fields.duration_minutes"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="min_per_lesson">{{
                            trans('Mindestfragen je Lektion')
                        }}</Label>
                        <Input
                            id="min_per_lesson"
                            v-model.number="fields.min_per_lesson"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="shuffle"
                            :model-value="fields.shuffle"
                            @update:model-value="
                                (value) => (fields.shuffle = value === true)
                            "
                        />
                        <Label for="shuffle">{{
                            trans('Fragen mischen')
                        }}</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="mt-4">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Pool-Übersicht')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-muted-foreground text-xs">
                    {{
                        trans(
                            'Der Fragenpool selbst wird hier noch nicht bearbeitet -- diese Übersicht zeigt, wie er heute dasteht.',
                        )
                    }}
                </p>
                <p class="text-sm">
                    {{ trans('Fragen im Pool') }}: {{ coverage.pool_size }}
                </p>

                <div>
                    <p class="mb-1 text-sm font-medium">
                        {{ trans('Abdeckung je Lektion') }}
                    </p>
                    <ul
                        class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm sm:grid-cols-4"
                    >
                        <li
                            v-for="lesson in coverage.lessons"
                            :key="lesson.lesson_id"
                            :class="
                                lesson.ok
                                    ? 'text-foreground'
                                    : 'text-destructive'
                            "
                        >
                            {{ lesson.lesson_id }}: {{ lesson.count }}/{{
                                coverage.min_per_lesson
                            }}
                        </li>
                    </ul>
                </div>

                <p
                    class="text-sm"
                    :class="
                        coverage.cross_ok
                            ? 'text-foreground'
                            : 'text-destructive'
                    "
                >
                    {{ trans('Cross-Fragen') }}: {{ coverage.cross_count }}/4
                </p>

                <div v-if="coverage.type_shares.length > 0">
                    <p class="mb-1 text-sm font-medium">
                        {{ trans('Typmischung') }}
                    </p>
                    <ul class="space-y-0.5 text-sm">
                        <li
                            v-for="share in coverage.type_shares"
                            :key="share.type"
                            :class="
                                share.percent < share.min ||
                                share.percent > share.max
                                    ? 'text-destructive'
                                    : 'text-foreground'
                            "
                        >
                            {{ share.type }}: {{ share.percent }}% ({{
                                trans('erwartet')
                            }}
                            {{ share.min }}-{{ share.max }}%)
                        </li>
                    </ul>
                </div>

                <p
                    class="text-sm"
                    :class="
                        coverage.difficulty3_share < 25
                            ? 'text-destructive'
                            : 'text-foreground'
                    "
                >
                    {{ trans('Schwierigkeit 3') }}:
                    {{ coverage.difficulty3_share }}% ({{
                        trans('erwartet mind. 25%')
                    }})
                </p>
            </CardContent>
        </Card>

        <div class="mt-8 flex flex-wrap items-center gap-3 border-t pt-6">
            <Button
                type="button"
                variant="outline"
                :disabled="validating"
                @click="runValidation"
            >
                {{ trans('Prüfen') }}
            </Button>
            <Button type="button" :disabled="saving" @click="saveDraft">
                {{ trans('Entwurf speichern') }}
            </Button>
            <Button
                v-if="pending_version && pending_version.status === 'draft'"
                type="button"
                variant="secondary"
                :disabled="acting"
                @click="submitForReview"
            >
                {{ trans('Zur Prüfung einreichen') }}
            </Button>
            <Button
                v-if="
                    pending_version &&
                    pending_version.status === 'review' &&
                    can_publish
                "
                type="button"
                :disabled="acting"
                @click="publishVersion"
            >
                {{ trans('Freigeben') }}
            </Button>
        </div>
    </PageContainer>
</template>
