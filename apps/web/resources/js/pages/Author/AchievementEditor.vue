<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import { edit, store, validate } from '@/routes/author/achievements/edit';
import { publish, submit } from '@/routes/author/quiz-versions';

type UnlockWhen = {
    type: '' | 'activity_completed' | 'track_passed' | 'first_solve';
    activity_type: string;
    key: string;
    track: string;
};

type AchievementFields = {
    slug: string;
    name: string;
    description: string;
    image: string;
    category: string;
    rarity: string;
    scope: string;
    points: number;
    is_hidden: boolean;
    sort_order: number;
    unlock_when: Partial<UnlockWhen> | null;
};

type PendingVersion = {
    id: number;
    status: 'draft' | 'review' | 'published';
    slug: string | null;
} | null;

const props = defineProps<{
    slug: string;
    fields: AchievementFields;
    is_new: boolean;
    pending_version: PendingVersion;
    can_publish: boolean;
}>();

const fields = ref<AchievementFields>({ ...props.fields });
const unlockType = ref<UnlockWhen['type']>(
    (fields.value.unlock_when?.type as UnlockWhen['type']) ?? '',
);
const unlockActivityType = ref(fields.value.unlock_when?.activity_type ?? '');
const unlockKey = ref(fields.value.unlock_when?.key ?? '');
const unlockTrack = ref(fields.value.unlock_when?.track ?? '');

const issues = ref<string[]>([]);
const validating = ref(false);
const saving = ref(false);
const acting = ref(false);

function payload() {
    return {
        ...fields.value,
        unlock_when:
            unlockType.value === ''
                ? null
                : {
                      type: unlockType.value,
                      activity_type: unlockActivityType.value || undefined,
                      key: unlockKey.value || undefined,
                      track: unlockTrack.value || undefined,
                  },
    };
}

async function runValidation() {
    validating.value = true;
    try {
        const result = await postJson<{ issues: string[] }>(
            validate.url({ slug: props.slug }),
            payload(),
        );
        issues.value = result.issues;
    } finally {
        validating.value = false;
    }
}

function saveDraft() {
    saving.value = true;
    router.post(store.url({ slug: props.slug }), payload(), {
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

const showsPendingForThisSlug = computed(
    () =>
        props.pending_version !== null &&
        props.pending_version.slug === props.slug,
);
</script>

<template>
    <Head :title="trans('Achievement bearbeiten: :slug', { slug })" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Start'), href: home() },
                { title: trans('Achievement bearbeiten'), href: edit(slug) },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{ trans('Achievement bearbeiten: :slug', { slug }) }}
            </h1>
            <Badge v-if="is_new" variant="secondary">{{ trans('Neu') }}</Badge>
            <Badge v-if="showsPendingForThisSlug" variant="outline">
                {{ statusLabels[pending_version!.status] }}
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
                <CardTitle class="text-base">{{ trans('Felder') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="name">{{ trans('Name') }}</Label>
                        <Input id="name" v-model="fields.name" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="description">{{
                            trans('Beschreibung')
                        }}</Label>
                        <Input id="description" v-model="fields.description" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="image">{{ trans('Bilddatei') }}</Label>
                        <Input
                            id="image"
                            v-model="fields.image"
                            placeholder="mein-achievement.png"
                        />
                        <p class="text-muted-foreground text-xs">
                            {{
                                trans(
                                    'Muss bereits unter public/images/achievements/ liegen -- kein Upload in diesem Editor.',
                                )
                            }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="category">{{ trans('Kategorie') }}</Label>
                        <Input
                            id="category"
                            v-model="fields.category"
                            placeholder="labs, dicom, platform, ..."
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="rarity">{{ trans('Seltenheit') }}</Label>
                        <Input
                            id="rarity"
                            v-model="fields.rarity"
                            placeholder="common, uncommon, ..."
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="scope">{{
                            trans('Geltungsbereich')
                        }}</Label>
                        <select
                            id="scope"
                            v-model="fields.scope"
                            class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option value="">{{ trans('(kein Wert)') }}</option>
                            <option value="personal">
                                {{ trans('Persönlich') }}
                            </option>
                            <option value="global">
                                {{ trans('Global (nur erste Person)') }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="points">{{ trans('Punkte') }}</Label>
                        <Input
                            id="points"
                            v-model.number="fields.points"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="sort_order">{{
                            trans('Sortierung')
                        }}</Label>
                        <Input
                            id="sort_order"
                            v-model.number="fields.sort_order"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="flex items-center gap-2 sm:col-span-2">
                        <Checkbox
                            id="is_hidden"
                            :model-value="fields.is_hidden"
                            @update:model-value="
                                (value) => (fields.is_hidden = value === true)
                            "
                        />
                        <Label for="is_hidden">{{
                            trans('Versteckt, bis freigeschaltet')
                        }}</Label>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="mt-4">
            <CardHeader>
                <CardTitle class="text-base">{{
                    trans('Auslösekriterium')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="unlock_type">{{ trans('Typ') }}</Label>
                    <select
                        id="unlock_type"
                        v-model="unlockType"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option value="">
                            {{ trans('Kein automatisches Kriterium') }}
                        </option>
                        <option value="activity_completed">
                            {{ trans('Aktivität abgeschlossen') }}
                        </option>
                        <option value="first_solve">
                            {{ trans('Erste Lösung eines Typs') }}
                        </option>
                        <option value="track_passed">
                            {{ trans('Track bestanden') }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="
                        unlockType === 'activity_completed' ||
                        unlockType === 'first_solve'
                    "
                    class="grid gap-4 sm:grid-cols-2"
                >
                    <div class="space-y-1.5">
                        <Label for="unlock_activity_type">{{
                            trans('Aktivitätstyp')
                        }}</Label>
                        <Input
                            id="unlock_activity_type"
                            v-model="unlockActivityType"
                            placeholder="node, lesson, ..."
                        />
                    </div>
                    <div
                        v-if="unlockType === 'activity_completed'"
                        class="space-y-1.5"
                    >
                        <Label for="unlock_key">{{
                            trans('Schlüssel (z. B. Node-Slug)')
                        }}</Label>
                        <Input id="unlock_key" v-model="unlockKey" />
                    </div>
                </div>

                <div v-if="unlockType === 'track_passed'" class="space-y-1.5">
                    <Label for="unlock_track">{{ trans('Track-Slug') }}</Label>
                    <Input id="unlock_track" v-model="unlockTrack" />
                </div>
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
                v-if="
                    showsPendingForThisSlug &&
                    pending_version!.status === 'draft'
                "
                type="button"
                variant="secondary"
                :disabled="acting"
                @click="submitForReview"
            >
                {{ trans('Zur Prüfung einreichen') }}
            </Button>
            <Button
                v-if="
                    showsPendingForThisSlug &&
                    pending_version!.status === 'review' &&
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
