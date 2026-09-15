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
import { show as showNode } from '@/routes/nodes';
import { index as studioIndex } from '@/routes/studio';
import {
    archive as archiveNode,
    duplicate as duplicateNode,
    edit as editNode,
    restore as restoreNode,
    store as storeNode,
} from '@/routes/studio/nodes';

type Status = 'draft' | 'published' | 'archived';
type Difficulty = 'easy' | 'medium' | 'hard' | 'insane';

type NodeRow = {
    slug: string;
    title: string;
    difficulty: Difficulty;
    category: string;
    status: Status;
    themenfeld_id: number | null;
    themenfeld_slug: string | null;
    points: number;
    estimated_minutes: number;
};

type Themenfeld = { id: number; slug: string };

const props = defineProps<{
    nodes: NodeRow[];
    themenfelder: Themenfeld[];
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
const difficultyFilter = ref<Difficulty | ''>('');
const categoryFilter = ref('');
const statusFilter = ref<Status | ''>('');
const themenfeldFilter = ref<number | ''>('');

const categories = computed(() =>
    [...new Set(props.nodes.map((node) => node.category))].sort(),
);

const filteredNodes = computed(() =>
    props.nodes.filter((node) => {
        if (
            search.value.trim() !== '' &&
            !node.title.toLowerCase().includes(search.value.trim().toLowerCase()) &&
            !node.slug.toLowerCase().includes(search.value.trim().toLowerCase())
        ) {
            return false;
        }

        if (difficultyFilter.value !== '' && node.difficulty !== difficultyFilter.value) {
            return false;
        }

        if (categoryFilter.value !== '' && node.category !== categoryFilter.value) {
            return false;
        }

        if (statusFilter.value !== '' && node.status !== statusFilter.value) {
            return false;
        }

        if (themenfeldFilter.value !== '' && node.themenfeld_id !== themenfeldFilter.value) {
            return false;
        }

        return true;
    }),
);

const newNode = ref({
    slug: '',
    title: '',
    themenfeld_id: props.themenfelder[0]?.id ?? 0,
    difficulty: 'easy' as Difficulty,
    category: '',
    interaction: 'terminal' as 'terminal' | 'scenario',
});
const showCreateForm = ref(false);

function createNode() {
    if (newNode.value.slug.trim() === '' || newNode.value.title.trim() === '') {
        return;
    }

    router.post(storeNode.url(), newNode.value);
}

function transition(node: NodeRow, action: 'archive' | 'restore') {
    const routes = { archive: archiveNode, restore: restoreNode };

    router.post(routes[action].url({ node: node.slug }), {}, { preserveScroll: true });
}

function duplicate(node: NodeRow) {
    router.post(duplicateNode.url({ node: node.slug }));
}
</script>

<template>
    <Head :title="trans('Nodes')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Nodes'), href: '' },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ trans('Nodes') }}</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{
                        trans(
                            'Archivierte Nodes sind für Lernende nicht mehr sichtbar, bleiben aber erhalten.',
                        )
                    }}
                </p>
            </div>
            <Button v-if="props.can_manage" @click="showCreateForm = !showCreateForm">
                {{ showCreateForm ? trans('Schließen') : trans('+ Neue Node') }}
            </Button>
        </div>

        <Card v-if="props.can_manage && showCreateForm" class="mb-6">
            <CardHeader>
                <CardTitle>{{ trans('Neue Node anlegen') }}</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-1.5">
                    <Label>{{ trans('Slug') }}</Label>
                    <Input
                        v-model="newNode.slug"
                        :placeholder="trans('z. B. dns-mismatch')"
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
                    <Input v-model="newNode.title" />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Kategorie') }}</Label>
                    <Input v-model="newNode.category" :placeholder="trans('z. B. netzwerk')" />
                </div>
                <div class="space-y-1.5">
                    <Label>{{ trans('Themenfeld') }}</Label>
                    <select
                        v-model.number="newNode.themenfeld_id"
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
                    <Label>{{ trans('Schwierigkeit') }}</Label>
                    <select
                        v-model="newNode.difficulty"
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
                <div class="space-y-1.5">
                    <Label>{{ trans('Interaktionstyp') }}</Label>
                    <select
                        v-model="newNode.interaction"
                        class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                    >
                        <option value="terminal">{{ trans('Terminal') }}</option>
                        <option value="scenario">{{ trans('Szenario') }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <Button @click="createNode">{{ trans('Anlegen') }}</Button>
                </div>
            </CardContent>
        </Card>

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <Input v-model="search" :placeholder="trans('Suche nach Titel oder Slug…')" />
            <select
                v-model="difficultyFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Schwierigkeiten') }}</option>
                <option v-for="(label, difficulty) in difficultyLabels" :key="difficulty" :value="difficulty">
                    {{ label }}
                </option>
            </select>
            <select
                v-model="categoryFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Kategorien') }}</option>
                <option v-for="category in categories" :key="category" :value="category">
                    {{ category }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Status') }}</option>
                <option v-for="(label, status) in statusLabels" :key="status" :value="status">
                    {{ label }}
                </option>
            </select>
            <select
                v-model="themenfeldFilter"
                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
            >
                <option value="">{{ trans('Alle Themenfelder') }}</option>
                <option v-for="themenfeld in props.themenfelder" :key="themenfeld.id" :value="themenfeld.id">
                    {{ themenfeld.slug }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-3">
            <Card v-for="node in filteredNodes" :key="node.slug">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{ node.title }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ node.slug }} · {{ difficultyLabels[node.difficulty] }} ·
                            {{ node.category }} ·
                            {{ trans(':points Punkte', { points: node.points }) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                node.status === 'published'
                                    ? 'default'
                                    : node.status === 'archived'
                                      ? 'secondary'
                                      : 'outline'
                            "
                        >
                            {{ statusLabels[node.status] }}
                        </Badge>
                        <Link :href="editNode.url({ node: node.slug })">
                            <Button variant="outline" size="sm">{{ trans('Bearbeiten') }}</Button>
                        </Link>
                        <a :href="showNode.url({ node: node.slug })" target="_blank" rel="noopener">
                            <Button variant="ghost" size="sm">{{ trans('Vorschau') }}</Button>
                        </a>
                        <template v-if="props.can_manage">
                            <Button variant="ghost" size="sm" @click="duplicate(node)">
                                {{ trans('Duplizieren') }}
                            </Button>
                            <Button
                                v-if="node.status !== 'archived'"
                                variant="ghost"
                                size="sm"
                                @click="transition(node, 'archive')"
                            >
                                {{ trans('Archivieren') }}
                            </Button>
                            <Button
                                v-if="node.status === 'archived'"
                                variant="outline"
                                size="sm"
                                @click="transition(node, 'restore')"
                            >
                                {{ trans('Wiederherstellen') }}
                            </Button>
                        </template>
                    </div>
                </CardHeader>
            </Card>

            <p v-if="filteredNodes.length === 0" class="text-muted-foreground text-sm">
                {{ trans('Keine Nodes gefunden.') }}
            </p>
        </div>
    </PageContainer>
</template>
