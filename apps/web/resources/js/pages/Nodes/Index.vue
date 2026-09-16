<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, CircleDot, Clock, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import GlobalFooter from '@/components/GlobalFooter.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { categoryLabels, difficultyVariant } from '@/lib/nodeCatalog';
import { trans } from '@/lib/trans';
import { show as showNode } from '@/routes/nodes';

type NodeSummary = {
    slug: string;
    title: string;
    difficulty: string;
    points: number;
    category: string;
    themenfeld: string;
    estimated_minutes: number;
    solved: boolean;
    status: 'offen' | 'begonnen' | 'abgeschlossen';
    related_lesson: { lesson_id: string; title: string } | null;
};

const props = defineProps<{ nodes: NodeSummary[] }>();

// Zweistufig gruppiert (Abschnitt 13): Themenfeld zuerst, weil Nodes aus
// unterschiedlichen Themenfeldern fachlich nichts miteinander zu tun haben
// -- category bleibt die Feingruppierung *innerhalb* eines Themenfelds.
const groups = computed(() => {
    const byThemenfeld = new Map<string, Map<string, NodeSummary[]>>();

    for (const node of props.nodes) {
        if (!byThemenfeld.has(node.themenfeld)) {
            byThemenfeld.set(node.themenfeld, new Map());
        }

        const byCategory = byThemenfeld.get(node.themenfeld)!;

        if (!byCategory.has(node.category)) {
            byCategory.set(node.category, []);
        }
        byCategory.get(node.category)!.push(node);
    }

    return Array.from(byThemenfeld.entries()).map(
        ([themenfeld, byCategory]) => ({
            themenfeld,
            categories: Array.from(byCategory.entries()),
        }),
    );
});
</script>

<template>
    <Head :title="trans('Herausforderungen')" />

    <PageContainer>
        <h1 class="mb-2 text-2xl font-semibold">
            {{ trans('Herausforderungen') }}
        </h1>
        <p class="text-muted-foreground mb-8">
            {{
                trans(
                    'Kaputte Umgebungen zum Reparieren — jede Node ist ein eigenständiges Szenario mit Flag und Punkten.',
                )
            }}
        </p>

        <div v-for="group in groups" :key="group.themenfeld" class="mb-12">
            <h2 class="mb-4 border-b pb-2 text-lg font-semibold">
                {{ trans(`themenfeld.${group.themenfeld}.title`) }}
            </h2>

            <div
                v-for="[category, categoryNodes] in group.categories"
                :key="category"
                class="mb-10"
            >
                <h3
                    class="text-muted-foreground mb-3 text-sm font-semibold tracking-wide uppercase"
                >
                    {{ categoryLabels[category] ?? category }}
                </h3>

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
                                        v-if="node.status === 'abgeschlossen'"
                                        class="size-4 shrink-0 text-green-600"
                                    />
                                    <CircleDot
                                        v-else-if="node.status === 'begonnen'"
                                        class="text-muted-foreground size-4 shrink-0"
                                    />
                                </div>
                            </CardHeader>
                            <CardContent class="flex flex-col gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        :variant="
                                            difficultyVariant[
                                                node.difficulty
                                            ] ?? 'outline'
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
                                </div>
                                <span
                                    v-if="node.related_lesson"
                                    class="text-muted-foreground text-xs"
                                >
                                    {{
                                        trans('Passende Lektion: :lesson', {
                                            lesson: node.related_lesson.title,
                                        })
                                    }}
                                </span>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </div>
    </PageContainer>

    <GlobalFooter />
</template>
