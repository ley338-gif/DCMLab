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
    estimated_minutes: number;
    solved: boolean;
    status: 'offen' | 'begonnen' | 'abgeschlossen';
    related_lesson: { lesson_id: string; title: string } | null;
};

const props = defineProps<{ nodes: NodeSummary[] }>();

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

    <PageContainer>
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
                            <div class="flex items-start justify-between gap-2">
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
    </PageContainer>

    <GlobalFooter />
</template>
