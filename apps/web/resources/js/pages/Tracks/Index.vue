<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
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
import { trans } from '@/lib/trans';
import { show as showTrack } from '@/routes/tracks';

type TrackSummary = {
    slug: string;
    title_key: string;
    // Nur gesetzt bei einem in Studio angelegten Track (ADR 0100) -- ein
    // per content:sync verwalteter Track hat weiterhin nur title_key.
    title: { de: string } | null;
    level: string;
    hours: number;
    status: string;
    lessons_count: number;
    themenfeld: string;
};

const props = defineProps<{
    tracks: TrackSummary[];
}>();

function trackTitle(track: TrackSummary): string {
    return track.title?.de ?? trans(track.title_key);
}

const levelLabels: Record<string, string> = {
    einsteiger: trans('Einsteiger'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};

// Gruppiert nach Themenfeld (Abschnitt 13), analog zu Nodes/Index.vue --
// die Reihenfolge kommt bereits sortiert vom Controller an, hier nur noch
// gruppiert.
const groups = computed(() => {
    const byThemenfeld = new Map<string, TrackSummary[]>();

    for (const track of props.tracks) {
        if (!byThemenfeld.has(track.themenfeld)) {
            byThemenfeld.set(track.themenfeld, []);
        }
        byThemenfeld.get(track.themenfeld)!.push(track);
    }

    return Array.from(byThemenfeld.entries()).map(([themenfeld, tracks]) => ({
        themenfeld,
        tracks,
    }));
});
</script>

<template>
    <Head :title="trans('Tracks')" />

    <PageContainer>
        <h1 class="mb-2 text-2xl font-semibold">
            {{ trans('Tracks') }}
        </h1>
        <p class="text-muted-foreground mb-8">
            {{
                trans(
                    'Lerne DICOM und PACS, indem du kaputte Umgebungen reparierst.',
                )
            }}
        </p>

        <div v-for="group in groups" :key="group.themenfeld" class="mb-12">
            <h2 class="mb-4 border-b pb-2 text-lg font-semibold">
                {{ trans(`themenfeld.${group.themenfeld}.title`) }}
            </h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <component
                    :is="track.status === 'published' ? Link : 'div'"
                    v-for="track in group.tracks"
                    :key="track.slug"
                    :href="
                        track.status === 'published'
                            ? showTrack(track.slug)
                            : undefined
                    "
                >
                    <Card
                        :class="{ 'opacity-60': track.status !== 'published' }"
                    >
                        <CardHeader>
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle>{{ trackTitle(track) }}</CardTitle>
                                <Lock
                                    v-if="track.status !== 'published'"
                                    class="text-muted-foreground size-4 shrink-0"
                                />
                            </div>
                            <CardDescription>
                                {{ levelLabels[track.level] ?? track.level }}
                                ·
                                {{
                                    trans(':hours Std.', {
                                        hours: track.hours,
                                    })
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">
                                {{
                                    trans(':count Lektionen', {
                                        count: track.lessons_count,
                                    })
                                }}
                            </span>
                            <Badge
                                :variant="
                                    track.status === 'published'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{
                                    track.status === 'published'
                                        ? trans('Verfügbar')
                                        : trans('Bald verfügbar')
                                }}
                            </Badge>
                        </CardContent>
                    </Card>
                </component>
            </div>
        </div>
    </PageContainer>

    <GlobalFooter />
</template>
