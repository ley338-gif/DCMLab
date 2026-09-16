<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    archive as archiveLab,
    edit as editLab,
    restore as restoreLab,
    store as storeLab,
} from '@/routes/studio/labs';

type Status = 'draft' | 'published' | 'archived';
type Difficulty = 'easy' | 'medium' | 'hard' | 'insane';

type LabRow = {
    slug: string;
    title: string;
    difficulty: Difficulty;
    status: Status;
    points: number;
    estimated_minutes: number;
    runtime_template: string | null;
    dataset: string | null;
};

const props = defineProps<{
    labs: LabRow[];
    can_manage: boolean;
}>();

const statusLabels: Record<Status, string> = {
    draft: trans('Entwurf'),
    published: trans('Veröffentlicht'),
    archived: trans('Archiviert'),
};

const difficultyLabels: Record<Difficulty, string> = {
    easy: trans('Leicht'),
    medium: trans('Mittel'),
    hard: trans('Schwer'),
    insane: trans('Extrem'),
};

const search = ref('');
const statusFilter = ref<Status | ''>('');

const filteredLabs = computed(() =>
    props.labs.filter((lab) => {
        if (
            search.value.trim() !== '' &&
            !lab.title
                .toLowerCase()
                .includes(search.value.trim().toLowerCase()) &&
            !lab.slug.toLowerCase().includes(search.value.trim().toLowerCase())
        ) {
            return false;
        }

        if (statusFilter.value !== '' && lab.status !== statusFilter.value) {
            return false;
        }

        return true;
    }),
);

const newLab = ref({
    slug: '',
    title: '',
    difficulty: 'easy' as Difficulty,
});
const showCreateForm = ref(false);

function createLab() {
    if (newLab.value.slug.trim() === '' || newLab.value.title.trim() === '') {
        return;
    }

    router.post(storeLab.url(), newLab.value);
}

function transition(lab: LabRow, action: 'archive' | 'restore') {
    const routes = { archive: archiveLab, restore: restoreLab };

    router.post(
        routes[action].url({ lab: lab.slug }),
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="trans('Labs')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Labs'), href: '' },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ trans('Labs') }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{
                        trans(
                            'Archivierte Labs sind für Lernende nicht mehr sichtbar, bleiben aber erhalten.',
                        )
                    }}
                </p>
            </div>
            <Button
                v-if="props.can_manage"
                @click="showCreateForm = !showCreateForm"
            >
                {{ showCreateForm ? trans('Schließen') : trans('+ Neues Lab') }}
            </Button>
        </div>

        <Card v-if="props.can_manage && showCreateForm" class="mb-6">
            <CardHeader>
                <CardTitle>{{ trans('Neues Lab anlegen') }}</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-1.5">
                    <Label>{{ trans('Slug') }}</Label>
                    <Input
                        v-model="newLab.slug"
                        :placeholder="trans('z. B. c-echo-connectivity')"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{
                            trans(
                                'Dient als URL und fachlicher Schlüssel — nach der ersten Veröffentlichung nicht mehr änderbar.',
                            )
                        }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Titel') }}</Label>
                    <Input v-model="newLab.title" />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Schwierigkeit') }}</Label>
                    <select
                        v-model="newLab.difficulty"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option
                            v-for="(label, difficulty) in difficultyLabels"
                            :key="difficulty"
                            :value="difficulty"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <Button @click="createLab">{{ trans('Anlegen') }}</Button>
                </div>
            </CardContent>
        </Card>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Input
                v-model="search"
                :placeholder="trans('Suche nach Titel oder Slug…')"
            />
            <select
                v-model="statusFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Status') }}</option>
                <option
                    v-for="(label, status) in statusLabels"
                    :key="status"
                    :value="status"
                >
                    {{ label }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-3">
            <Card v-for="lab in filteredLabs" :key="lab.slug">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{ lab.title }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ lab.slug }} ·
                            {{ difficultyLabels[lab.difficulty] }} ·
                            {{
                                trans(':points Punkte', { points: lab.points })
                            }}
                            <template
                                v-if="
                                    lab.runtime_template === null ||
                                    lab.dataset === null
                                "
                            >
                                ·
                                {{ trans('Runtime unvollständig') }}
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                lab.status === 'published'
                                    ? 'default'
                                    : lab.status === 'archived'
                                      ? 'secondary'
                                      : 'outline'
                            "
                        >
                            {{ statusLabels[lab.status] }}
                        </Badge>
                        <Link :href="editLab.url({ lab: lab.slug })">
                            <Button variant="outline" size="sm">{{
                                trans('Bearbeiten')
                            }}</Button>
                        </Link>
                        <template v-if="props.can_manage">
                            <Button
                                v-if="lab.status !== 'archived'"
                                variant="ghost"
                                size="sm"
                                @click="transition(lab, 'archive')"
                            >
                                {{ trans('Archivieren') }}
                            </Button>
                            <Button
                                v-if="lab.status === 'archived'"
                                variant="outline"
                                size="sm"
                                @click="transition(lab, 'restore')"
                            >
                                {{ trans('Wiederherstellen') }}
                            </Button>
                        </template>
                    </div>
                </CardHeader>
            </Card>

            <p
                v-if="filteredLabs.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Keine Labs gefunden.') }}
            </p>
        </div>
    </PageContainer>
</template>
