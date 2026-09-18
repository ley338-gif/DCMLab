<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    BookOpenText,
    ChevronRight,
    FlaskConical,
    GraduationCap,
    Layers,
    Network,
} from '@lucide/vue';
import GlobalFooter from '@/components/GlobalFooter.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { difficultyLabels } from '@/lib/labCatalog';
import { trans } from '@/lib/trans';
import { register } from '@/routes';
import { index as labsIndex, show as showLab } from '@/routes/labs';
import { index as tracksIndex, show as showTrack } from '@/routes/tracks';

type TrackHighlight = {
    slug: string;
    title_key: string;
    title: { de: string } | null;
    level: string;
    hours: number;
    lessons_count: number;
};

type LabHighlight = {
    slug: string;
    title: string;
    scenario_title: string;
    difficulty: string;
    estimated_minutes: number;
};

const props = defineProps<{
    highlight_tracks: TrackHighlight[];
    highlight_labs: LabHighlight[];
}>();

function trackTitle(track: TrackHighlight): string {
    return track.title?.de ?? trans(track.title_key);
}

const levelLabels: Record<string, string> = {
    einsteiger: trans('Einsteiger'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};

const learningModel = [
    { icon: GraduationCap, label: trans('Track') },
    { icon: BookOpenText, label: trans('Lesson') },
    { icon: Network, label: trans('Herausforderung') },
    { icon: FlaskConical, label: trans('Lab') },
    { icon: Award, label: trans('Achievement') },
];
</script>

<template>
    <Head :title="trans('DICOM, PACS und Interoperabilität lernen')" />

    <section class="landing-hero">
        <div class="mx-auto max-w-[1320px] px-6 py-20 sm:py-28">
            <div class="max-w-[520px]">
                <p
                    class="text-chart-2 mb-3 text-sm font-semibold tracking-wide uppercase"
                >
                    DCMLab
                </p>
                <h1 class="text-4xl font-semibold sm:text-5xl">
                    {{
                        trans(
                            'DICOM, PACS und Interoperabilität verstehen. Nicht nur auswendig lernen.',
                        )
                    }}
                </h1>
                <p class="mt-6 text-lg opacity-90">
                    {{
                        trans(
                            'Interaktive Lektionen, Herausforderungen und Labs für Healthcare-IT und PACS-Administration.',
                        )
                    }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <Link
                        :href="register()"
                        class="bg-background text-foreground rounded-md px-5 py-2.5 text-sm font-semibold hover:opacity-90"
                    >
                        {{ trans('Jetzt starten') }}
                    </Link>
                    <Link
                        :href="tracksIndex()"
                        class="landing-hero-feature rounded-md px-5 py-2.5 text-sm font-semibold"
                    >
                        {{ trans('Tracks entdecken') }}
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-[1320px] px-6 py-16">
        <div class="mb-8 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold">{{ trans('Tracks') }}</h2>
                <p class="text-muted-foreground mt-1">
                    {{
                        trans(
                            'Strukturierte Lernpfade durch DICOM, PACS und Interoperabilität.',
                        )
                    }}
                </p>
            </div>
            <Link
                :href="tracksIndex()"
                class="text-sm font-medium whitespace-nowrap hover:underline"
            >
                {{ trans('Alle Tracks ansehen') }}
                <ChevronRight
                    class="ml-0.5 inline size-3.5"
                    aria-hidden="true"
                />
            </Link>
        </div>

        <p
            v-if="props.highlight_tracks.length === 0"
            class="text-muted-foreground text-sm"
        >
            {{ trans('Neue Tracks werden bald verfügbar sein.') }}
        </p>
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="track in props.highlight_tracks"
                :key="track.slug"
                :href="showTrack(track.slug)"
            >
                <Card
                    class="hover:border-foreground/30 h-full transition-colors"
                >
                    <CardHeader>
                        <CardTitle>{{ trackTitle(track) }}</CardTitle>
                        <CardDescription>
                            {{ levelLabels[track.level] ?? track.level }} ·
                            {{ trans(':hours Std.', { hours: track.hours }) }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <span class="text-muted-foreground text-sm">
                            {{
                                trans(':count Lektionen', {
                                    count: track.lessons_count,
                                })
                            }}
                        </span>
                    </CardContent>
                </Card>
            </Link>
        </div>
    </section>

    <section class="border-t">
        <div class="mx-auto max-w-[1320px] px-6 py-16">
            <div class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-semibold">
                        {{ trans('Praxis statt nur Theorie') }}
                    </h2>
                    <p class="text-muted-foreground mt-1 max-w-2xl">
                        {{
                            trans(
                                'Echte Labs mit einer echten Orthanc-/DCMTK-Laufzeitumgebung — kein Multiple-Choice-Quiz.',
                            )
                        }}
                    </p>
                </div>
                <Link
                    :href="labsIndex()"
                    class="text-sm font-medium whitespace-nowrap hover:underline"
                >
                    {{ trans('Alle Labs ansehen') }}
                    <ChevronRight
                        class="ml-0.5 inline size-3.5"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <p
                v-if="props.highlight_labs.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Neue Labs werden bald verfügbar sein.') }}
            </p>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <Link
                    v-for="lab in props.highlight_labs"
                    :key="lab.slug"
                    :href="showLab(lab.slug)"
                >
                    <Card
                        class="hover:border-foreground/30 h-full transition-colors"
                    >
                        <CardHeader>
                            <div class="flex items-start gap-2">
                                <FlaskConical
                                    class="text-chart-2 mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <CardTitle>{{ lab.title }}</CardTitle>
                            </div>
                            <CardDescription v-if="lab.scenario_title">
                                {{ lab.scenario_title }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{
                                    difficultyLabels[lab.difficulty] ??
                                    lab.difficulty
                                }}
                            </Badge>
                            <span class="text-muted-foreground text-sm">
                                {{
                                    trans('~:minutes Min.', {
                                        minutes: lab.estimated_minutes,
                                    })
                                }}
                            </span>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-[1320px] px-6 py-16">
        <div class="mb-10 text-center">
            <h2 class="text-2xl font-semibold">
                {{ trans('Strukturiert lernen, nicht nur lesen') }}
            </h2>
            <p class="text-muted-foreground mt-1">
                {{
                    trans(
                        'Jeder Track führt dich vom Grundlagenwissen bis zur echten Praxisübung.',
                    )
                }}
            </p>
        </div>

        <div
            class="flex flex-col items-stretch gap-2 lg:flex-row lg:items-center lg:justify-center lg:gap-3"
        >
            <template v-for="(step, index) in learningModel" :key="step.label">
                <div
                    class="landing-model-step flex flex-1 flex-col items-center gap-2 rounded-lg px-4 py-5 text-center lg:w-36 lg:flex-none"
                >
                    <component
                        :is="step.icon"
                        class="text-chart-2 size-6"
                        aria-hidden="true"
                    />
                    <span class="text-sm font-medium">{{ step.label }}</span>
                </div>
                <ChevronRight
                    v-if="index < learningModel.length - 1"
                    class="landing-model-arrow mx-auto size-5 rotate-90 lg:mx-0 lg:rotate-0"
                    aria-hidden="true"
                />
            </template>
        </div>
    </section>

    <section class="landing-cta-band">
        <div
            class="mx-auto flex max-w-[1320px] flex-col items-center gap-5 px-6 py-16 text-center"
        >
            <Layers class="size-8" aria-hidden="true" />
            <h2 class="text-2xl font-semibold sm:text-3xl">
                {{ trans('Bereit, dein erstes DICOM-Problem zu lösen?') }}
            </h2>
            <Link
                :href="register()"
                class="bg-background text-foreground rounded-md px-6 py-3 text-sm font-semibold hover:opacity-90"
            >
                {{ trans('Kostenlos starten') }}
            </Link>
        </div>
    </section>

    <GlobalFooter />
</template>
