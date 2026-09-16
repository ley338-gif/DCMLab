<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { trans } from '@/lib/trans';
import { start as startLab } from '@/routes/labs';

type LabProps = {
    slug: string;
    title: string;
    scenario_title: string;
    difficulty: string;
    points: number;
    estimated_minutes: number;
};

const props = defineProps<{
    lab: LabProps;
    briefing_html: string | null;
    attempt: { status: string } | null;
}>();

const statusLabels: Record<string, string> = {
    started: 'Begonnen',
    solved: 'Abgeschlossen',
    abandoned: 'Abgebrochen',
};
</script>

<template>
    <Head :title="lab.title" />

    <div class="mx-auto max-w-4xl px-6 pt-10 pb-16">
        <h1 class="mb-1 text-2xl font-semibold">{{ props.lab.title }}</h1>
        <p v-if="props.lab.scenario_title" class="text-muted-foreground mb-4">
            {{ props.lab.scenario_title }}
        </p>

        <div class="mb-6 flex flex-wrap items-center gap-2">
            <Badge variant="outline">{{ props.lab.difficulty }}</Badge>
            <Badge variant="outline"
                >{{ props.lab.points }} {{ trans('Pkt.') }}</Badge
            >
            <Badge variant="outline"
                >{{ props.lab.estimated_minutes }} {{ trans('Min.') }}</Badge
            >
            <Badge v-if="attempt">{{
                statusLabels[attempt.status] ?? attempt.status
            }}</Badge>
        </div>

        <div v-if="briefing_html" class="lesson-prose" v-html="briefing_html" />

        <p v-else class="text-muted-foreground">
            {{ trans('Noch keine Anleitung hinterlegt.') }}
        </p>

        <Form
            v-if="!attempt"
            v-bind="startLab.form(lab.slug)"
            v-slot="{ processing }"
            class="mt-6"
        >
            <Button type="submit" :disabled="processing">
                {{ trans('Lab starten') }}
            </Button>
        </Form>
    </div>
</template>
