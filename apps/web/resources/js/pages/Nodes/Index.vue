<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { dashboard, leaderboard, login, register } from '@/routes';
import { index as glossaryIndex } from '@/routes/glossary';
import { impressum, datenschutz, nutzungsbedingungen } from '@/routes/legal';
import { show as showNode } from '@/routes/nodes';

type NodeSummary = {
    slug: string;
    title: string;
    difficulty: string;
    points: number;
    category: string;
    estimated_minutes: number;
    solved: boolean;
};

const props = defineProps<{ nodes: NodeSummary[] }>();

const page = usePage();

const categoryLabels: Record<string, string> = {
    netzwerk: trans('Netzwerk'),
    datenmodell: trans('Datenmodell'),
    bildgebung: trans('Bildgebung'),
    integration: trans('Integration'),
    security: trans('Security'),
};

const difficultyVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    easy: 'outline',
    medium: 'secondary',
    hard: 'default',
    insane: 'destructive',
};

const groups = computed(() => {
    const byCategory = new Map<string, NodeSummary[]>();

    for (const node of props.nodes) {
        if (!byCategory.has(node.category)) {
            byCategory.set(node.category, []);
        }
        byCategory.get(node.category)!.push(node);
    }

    return Array.from(byCategory.entries());
});
</script>

<template>
    <Head :title="trans('Labs')" />

    <div class="bg-background min-h-screen">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4"
            >
                <Link :href="dashboard()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-4 text-sm">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboard()">{{
                            trans('Dashboard')
                        }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">{{ trans('Log in') }}</Link>
                        <Link
                            :href="register()"
                            class="text-primary font-medium"
                            >{{ trans('Register') }}</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <h1 class="mb-2 text-2xl font-semibold">{{ trans('Labs') }}</h1>
            <p class="text-muted-foreground mb-8">
                {{
                    trans(
                        'Kaputte Umgebungen zum Reparieren — jede Node ist ein eigenständiges Szenario mit Flag und Punkten.',
                    )
                }}
            </p>

            <div
                v-for="[category, categoryNodes] in groups"
                :key="category"
                class="mb-10"
            >
                <h2
                    class="text-muted-foreground mb-3 text-sm font-semibold tracking-wide uppercase"
                >
                    {{ categoryLabels[category] ?? category }}
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="node in categoryNodes"
                        :key="node.slug"
                        :href="showNode(node.slug)"
                    >
                        <Card>
                            <CardHeader>
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <CardTitle>{{ node.title }}</CardTitle>
                                    <CheckCircle2
                                        v-if="node.solved"
                                        class="size-4 shrink-0 text-green-600"
                                    />
                                </div>
                            </CardHeader>
                            <CardContent
                                class="flex flex-wrap items-center gap-2"
                            >
                                <Badge
                                    :variant="
                                        difficultyVariant[node.difficulty] ??
                                        'outline'
                                    "
                                >
                                    {{ node.difficulty }}
                                </Badge>
                                <span
                                    class="text-muted-foreground flex items-center gap-1 text-xs"
                                >
                                    <Trophy class="size-3.5" />
                                    {{
                                        trans(':points Pkt.', {
                                            points: node.points,
                                        })
                                    }}
                                </span>
                                <span
                                    class="text-muted-foreground flex items-center gap-1 text-xs"
                                >
                                    <Clock class="size-3.5" />
                                    {{
                                        trans(':minutes Min.', {
                                            minutes: node.estimated_minutes,
                                        })
                                    }}
                                </span>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </main>

        <footer class="border-t">
            <div
                class="text-muted-foreground mx-auto flex max-w-5xl flex-wrap gap-4 px-6 py-6 text-sm"
            >
                <Link :href="leaderboard()">{{ trans('Leaderboard') }}</Link>
                <Link :href="glossaryIndex()">{{ trans('Glossar') }}</Link>
                <Link :href="impressum()">{{ trans('Impressum') }}</Link>
                <Link :href="datenschutz()">{{ trans('Datenschutz') }}</Link>
                <Link :href="nutzungsbedingungen()">{{
                    trans('Nutzungsbedingungen')
                }}</Link>
            </div>
        </footer>
    </div>
</template>
