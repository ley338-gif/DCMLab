<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    CircleDot,
    Clock,
    FlaskConical,
    Trophy,
} from '@lucide/vue';
import GlobalFooter from '@/components/GlobalFooter.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { difficultyLabels, difficultyVariant } from '@/lib/labCatalog';
import { trans } from '@/lib/trans';
import { show as showLab } from '@/routes/labs';

type LabSummary = {
    slug: string;
    title: string;
    scenario_title: string;
    difficulty: string;
    points: number;
    estimated_minutes: number;
    status: 'not_started' | 'in_progress' | 'solved';
};

const props = defineProps<{ labs: LabSummary[] }>();

function ctaLabel(lab: LabSummary): string {
    if (lab.status === 'solved') return trans('Erneut öffnen');
    if (lab.status === 'in_progress') return trans('Fortsetzen');

    return trans('Starten');
}
</script>

<template>
    <Head :title="trans('Labs')" />

    <PageContainer>
        <h1 class="mb-2 text-2xl font-semibold">{{ trans('Labs') }}</h1>
        <p class="text-muted-foreground mb-8">
            {{
                trans(
                    'Arbeite in echten DICOM-Testumgebungen mit Orthanc und DCMTK.',
                )
            }}
        </p>

        <p v-if="props.labs.length === 0" class="text-muted-foreground text-sm">
            {{ trans('Derzeit sind keine Labs verfügbar.') }}
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2">
            <Link
                v-for="lab in props.labs"
                :key="lab.slug"
                :href="showLab(lab.slug)"
            >
                <Card
                    class="hover:border-foreground/30 h-full transition-colors"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-2">
                                <FlaskConical
                                    class="text-chart-2 mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <CardTitle>{{ lab.title }}</CardTitle>
                            </div>
                            <CheckCircle2
                                v-if="lab.status === 'solved'"
                                class="size-4 shrink-0 text-green-600"
                                aria-hidden="true"
                            />
                            <CircleDot
                                v-else-if="lab.status === 'in_progress'"
                                class="text-muted-foreground size-4 shrink-0"
                                aria-hidden="true"
                            />
                        </div>
                        <CardDescription v-if="lab.scenario_title">
                            {{ lab.scenario_title }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge
                                :variant="
                                    difficultyVariant[lab.difficulty] ??
                                    'outline'
                                "
                            >
                                {{
                                    difficultyLabels[lab.difficulty] ??
                                    lab.difficulty
                                }}
                            </Badge>
                            <span
                                class="text-muted-foreground flex items-center gap-1 text-xs"
                            >
                                <Trophy class="size-3.5" aria-hidden="true" />
                                {{
                                    trans(':points Pkt.', {
                                        points: lab.points,
                                    })
                                }}
                            </span>
                            <span
                                class="text-muted-foreground flex items-center gap-1 text-xs"
                            >
                                <Clock class="size-3.5" aria-hidden="true" />
                                {{
                                    trans('~:minutes Min.', {
                                        minutes: lab.estimated_minutes,
                                    })
                                }}
                            </span>
                        </div>
                        <span
                            class="text-primary inline-flex items-center gap-1 text-sm font-medium"
                        >
                            {{ ctaLabel(lab) }}
                            <ArrowRight class="size-3.5" aria-hidden="true" />
                        </span>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </PageContainer>

    <GlobalFooter />
</template>
