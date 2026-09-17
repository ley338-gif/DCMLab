<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    Clock,
    ListChecks,
    Lock,
    SignalMedium,
} from '@lucide/vue';
import { computed } from 'vue';
import { trans } from '@/lib/trans';
import { show as showLesson } from '@/routes/lessons';
import { index as tracksIndex, show as showTrack } from '@/routes/tracks';

const props = defineProps<{
    track: { slug: string; title_key: string };
    title: string;
    teaser: string;
    durationMinutes: number;
    level: string;
    objectivesCount: number;
    positionInTrack: number | null;
    trackLessonsCount: number;
    requires: { lesson_id: string; title: string; completed: boolean }[];
}>();

const unmetRequires = computed(() =>
    props.requires.filter((required) => !required.completed),
);

const levelLabels: Record<string, string> = {
    einsteiger: trans('Grundlagen'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};
</script>

<template>
    <header class="lesson-hero">
        <nav
            class="lesson-breadcrumb"
            :aria-label="trans('Brotkrumen-Navigation')"
        >
            <Link :href="tracksIndex()">{{ trans('Tracks') }}</Link>
            <ChevronRight class="size-3.5" aria-hidden="true" />
            <Link :href="showTrack(track.slug)">{{
                trans(track.title_key)
            }}</Link>
        </nav>

        <p v-if="positionInTrack" class="lesson-hero-eyebrow">
            {{
                trans('Lektion :position von :total', {
                    position: positionInTrack,
                    total: trackLessonsCount,
                })
            }}
        </p>

        <h1 class="lesson-hero-title">{{ title }}</h1>
        <p v-if="teaser" class="lesson-hero-teaser">{{ teaser }}</p>

        <ul class="lesson-hero-meta">
            <li>
                <Clock class="size-4" aria-hidden="true" />
                {{ trans(':minutes Minuten', { minutes: durationMinutes }) }}
            </li>
            <li v-if="levelLabels[level]">
                <SignalMedium class="size-4" aria-hidden="true" />
                {{ levelLabels[level] }}
            </li>
            <li v-if="objectivesCount > 0">
                <ListChecks class="size-4" aria-hidden="true" />
                {{ trans(':count Lernziele', { count: objectivesCount }) }}
            </li>
        </ul>

        <p
            v-if="unmetRequires.length"
            class="lesson-hero-locked-notice"
            role="status"
        >
            <Lock class="size-4 shrink-0" aria-hidden="true" />
            <span>
                {{ trans('Setzt voraus, dass du zuerst liest') }}:
                <template
                    v-for="(required, index) in unmetRequires"
                    :key="required.lesson_id"
                >
                    <span v-if="index > 0">, </span>
                    <Link :href="showLesson(required.lesson_id)">{{
                        required.title
                    }}</Link>
                </template>
                — {{ trans('du kannst trotzdem hier weiterlesen') }}.
            </span>
        </p>

        <p v-else-if="requires.length" class="lesson-hero-requires">
            {{ trans('Vorher') }}:
            <template
                v-for="(required, index) in requires"
                :key="required.lesson_id"
            >
                <span v-if="index > 0">, </span>
                <Link :href="showLesson(required.lesson_id)">{{
                    required.title
                }}</Link>
            </template>
        </p>
    </header>
</template>
