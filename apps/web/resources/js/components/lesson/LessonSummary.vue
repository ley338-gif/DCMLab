<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import { trans } from '@/lib/trans';

defineProps<{ objectives: string[] }>();
</script>

<template>
    <section
        v-if="objectives.length"
        class="lesson-summary"
        aria-label="Zusammenfassung"
    >
        <p class="lesson-summary-title">{{ trans('Zusammenfassung') }}</p>
        <p class="lesson-summary-lead">
            {{ trans('Nach dieser Lektion solltest du wissen:') }}
        </p>
        <ul>
            <li v-for="(objective, index) in objectives" :key="index">
                <CheckCircle2 class="size-4 shrink-0" aria-hidden="true" />
                <span>{{ objective }}</span>
            </li>
        </ul>
    </section>
</template>
