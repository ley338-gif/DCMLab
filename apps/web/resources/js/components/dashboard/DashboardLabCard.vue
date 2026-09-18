<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, FlaskConical } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { difficultyLabels } from '@/lib/labCatalog';
import { trans } from '@/lib/trans';
import { show as showLab } from '@/routes/labs';

type Lab = {
    slug: string;
    title: string;
    difficulty: string;
    estimated_minutes: number;
    status: 'not_started' | 'in_progress' | 'solved';
};

const props = defineProps<{
    lab: Lab;
}>();

const ctaLabel = computed(() =>
    props.lab.status === 'not_started' ? trans('Lab starten') : trans('Weiter'),
);
</script>

<template>
    <Link :href="showLab(lab.slug)" class="block h-full">
        <div
            class="hover:border-foreground/30 flex h-full flex-col justify-between gap-3 rounded-lg border p-4 transition-colors"
        >
            <div>
                <div class="flex items-start gap-2">
                    <FlaskConical
                        class="text-muted-foreground mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="font-medium">{{ lab.title }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <Badge variant="outline">
                        {{ difficultyLabels[lab.difficulty] ?? lab.difficulty }}
                    </Badge>
                    <span class="text-muted-foreground text-sm">
                        {{
                            trans('~:minutes Min.', {
                                minutes: lab.estimated_minutes,
                            })
                        }}
                    </span>
                </div>
            </div>

            <span
                v-if="lab.status === 'solved'"
                class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-4" aria-hidden="true" />
                {{ trans('abgeschlossen') }}
            </span>
            <span
                v-else
                class="text-primary inline-flex items-center gap-1 text-sm font-medium"
            >
                {{ ctaLabel }}
                <ArrowRight class="size-3.5" aria-hidden="true" />
            </span>
        </div>
    </Link>
</template>
