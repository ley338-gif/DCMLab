<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { dashboard, login, register } from '@/routes';
import { show as showTrack } from '@/routes/tracks';

type TrackSummary = {
    slug: string;
    title_key: string;
    level: string;
    hours: number;
    status: string;
    lessons_count: number;
};

defineProps<{
    tracks: TrackSummary[];
}>();

const page = usePage();

const levelLabels: Record<string, string> = {
    einsteiger: trans('Einsteiger'),
    aufbau: trans('Aufbau'),
    fortgeschritten: trans('Fortgeschritten'),
};
</script>

<template>
    <Head :title="trans('Tracks')" />

    <div class="bg-background min-h-screen">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4"
            >
                <Link :href="dashboard()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-4 text-sm">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboard()">{{
                            trans('Dashboard')
                        }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">{{ trans('Log in') }}</Link>
                        <Link
                            :href="register()"
                            class="text-primary font-medium"
                            >{{ trans('Register') }}</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
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

            <div class="grid gap-4 sm:grid-cols-2">
                <component
                    :is="track.status === 'published' ? Link : 'div'"
                    v-for="track in tracks"
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
                                <CardTitle>{{
                                    trans(track.title_key)
                                }}</CardTitle>
                                <Lock
                                    v-if="track.status !== 'published'"
                                    class="text-muted-foreground size-4 shrink-0"
                                />
                            </div>
                            <CardDescription>
                                {{ levelLabels[track.level] ?? track.level }} ·
                                {{
                                    trans(':hours Std.', { hours: track.hours })
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
        </main>
    </div>
</template>
