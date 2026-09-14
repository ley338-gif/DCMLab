<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';

export type ScenarioOption = { id: string; label: string };
export type ScenarioLogEntry = { prompt: string; chosen_label: string };
export type ScenarioState = {
    step_id: string;
    prompt: string;
    options: ScenarioOption[];
    terminal: boolean;
    outcome: 'correct' | 'wrong' | null;
    log: ScenarioLogEntry[];
};

defineProps<{ scenario: ScenarioState }>();

const emit = defineEmits<{
    choose: [optionId: string];
    restart: [];
}>();
</script>

<template>
    <div class="space-y-4">
        <Card>
            <CardHeader>
                <CardTitle class="text-sm">{{ trans('Dialog') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-sm">{{ scenario.prompt }}</p>

                <div v-if="!scenario.terminal" class="flex flex-col gap-2">
                    <Button
                        v-for="option in scenario.options"
                        :key="option.id"
                        type="button"
                        variant="outline"
                        class="justify-start text-left whitespace-normal"
                        @click="emit('choose', option.id)"
                    >
                        {{ option.label }}
                    </Button>
                </div>

                <div v-else class="space-y-3">
                    <p
                        class="text-sm font-medium"
                        :class="
                            scenario.outcome === 'correct'
                                ? 'text-green-600'
                                : 'text-destructive'
                        "
                    >
                        {{
                            scenario.outcome === 'correct'
                                ? trans('Richtiger Weg.')
                                : trans('Das war nicht der richtige Weg.')
                        }}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="emit('restart')"
                    >
                        {{ trans('Neu starten') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="scenario.log.length">
            <CardHeader>
                <CardTitle class="text-sm">{{ trans('Verlauf') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 text-sm">
                <div v-for="(entry, index) in scenario.log" :key="index">
                    <p class="text-muted-foreground">{{ entry.prompt }}</p>
                    <p>→ {{ entry.chosen_label }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
