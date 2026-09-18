<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, FlaskConical } from '@lucide/vue';
import SandboxPanel from '@/components/SandboxPanel.vue';
import { Badge } from '@/components/ui/badge';
import { trans } from '@/lib/trans';
import { show as showNode } from '@/routes/nodes';

type RelatedNode = {
    slug: string;
    title: string;
    difficulty: string;
    points: number;
};

defineProps<{
    lessonId: string;
    needsSandbox: boolean;
    dataset: { note: string | null; file_count: number | null } | null;
    relatedNode: RelatedNode | null;
}>();
</script>

<template>
    <section
        v-if="needsSandbox || relatedNode"
        class="practice-task"
        aria-label="Praxis"
    >
        <div class="practice-task-head">
            <FlaskConical class="size-4" aria-hidden="true" />
            <span>{{ trans('Praxis') }}</span>
        </div>

        <p v-if="dataset?.note" class="practice-task-note">
            {{ dataset.note }}
            <template v-if="dataset.file_count">
                ·
                {{
                    trans(':count Dateien, synthetisch', {
                        count: dataset.file_count,
                    })
                }}
            </template>
        </p>

        <SandboxPanel v-if="needsSandbox" :lesson-id="lessonId" />

        <Link
            v-if="relatedNode"
            :href="showNode(relatedNode.slug)"
            class="practice-task-related-node"
        >
            <span>
                {{
                    trans('Verknüpfte Herausforderung: „:title"', {
                        title: relatedNode.title,
                    })
                }}
            </span>
            <Badge variant="outline">{{ relatedNode.difficulty }}</Badge>
            <Badge variant="outline">{{
                trans(':points Pkt.', { points: relatedNode.points })
            }}</Badge>
            <ArrowRight class="size-4" aria-hidden="true" />
        </Link>
    </section>
</template>
