<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, FlaskConical } from '@lucide/vue';
import { trans } from '@/lib/trans';
import { show as showLab } from '@/routes/labs';

type LabSummary = {
    slug: string;
    title: string;
    estimated_minutes: number;
    status: string;
};

defineProps<{
    lab: LabSummary | null;
}>();
</script>

<template>
    <section v-if="lab" class="practice-task" aria-label="Praxis">
        <div class="practice-task-head">
            <FlaskConical class="size-4" aria-hidden="true" />
            <span>{{ trans('Lab') }}</span>
        </div>

        <div class="practice-task-lab-card">
            <div>
                <p class="font-medium">{{ lab.title }}</p>
                <p class="text-muted-foreground text-sm">
                    {{
                        trans('Dauer :minutes Min.', {
                            minutes: lab.estimated_minutes,
                        })
                    }}
                </p>
            </div>

            <span
                v-if="lab.status === 'solved'"
                class="text-sm font-medium text-emerald-600"
            >
                <CheckCircle2 class="mr-1 inline size-4" aria-hidden="true" />
                {{ trans('abgeschlossen') }}
            </span>
            <Link
                v-else
                :href="showLab(lab.slug)"
                class="practice-task-lab-card-cta"
            >
                {{
                    lab.status === 'not_started'
                        ? trans('Lab starten')
                        : trans('Weiter')
                }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
        </div>
    </section>
</template>
