<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
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
import { show as showLesson } from '@/routes/lessons';
import { index as reviewIndex } from '@/routes/review';
import { show as showTrack } from '@/routes/tracks';
import { start as startExam } from '@/routes/tracks/exam';
import type { Achievement } from '@/types/achievement';

type TrackExamStatus = {
    available: boolean;
    defined: boolean;
    passed: boolean;
    passed_at: string | null;
};

type TrackProgress = {
    slug: string;
    title_key: string;
    lessons_count: number;
    completed_lessons_count: number;
    exam: TrackExamStatus;
};

type RecentLesson = {
    lesson_id: string;
    title: string;
    status: string;
    started_at: string;
    completed_at: string | null;
};

const props = defineProps<{
    profile: {
        points: number;
        rank: string;
        skill_vector: Record<string, number>;
    };
    tracks: TrackProgress[];
    recent_lessons: RecentLesson[];
    achievements: Achievement[];
    due_reviews_count: number;
}>();

const rankLabels: Record<string, string> = {
    novice: trans('Novize'),
    operator: trans('Operator'),
    administrator: trans('Administrator'),
    architect: trans('Architekt'),
    standard_bearer: trans('Standard Bearer'),
};

const unlockedAchievementsCount = computed(
    () =>
        props.achievements.filter((achievement) => achievement.unlocked).length,
);

// Zeigt die zuletzt relevanten Achievements zuerst: freigeschaltete nach
// Datum, danach die noch gesperrten in ihrer Registry-Reihenfolge.
const dashboardAchievements = computed(() => {
    const unlocked = props.achievements
        .filter((achievement) => achievement.unlocked)
        .sort((a, b) =>
            (b.unlocked_at ?? '').localeCompare(a.unlocked_at ?? ''),
        );
    const locked = props.achievements.filter(
        (achievement) => !achievement.unlocked,
    );

    return [...unlocked, ...locked].slice(0, 6);
});

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="trans('Dashboard')" />

    <PageContainer>
        <div class="flex flex-col gap-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{ trans('Punkte') }}</CardDescription>
                        <CardTitle class="text-3xl">{{
                            props.profile.points
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{ trans('Rang') }}</CardDescription>
                        <CardTitle class="text-3xl">{{
                            rankLabels[props.profile.rank] ?? props.profile.rank
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>{{
                            trans('Lektionen erledigt')
                        }}</CardDescription>
                        <CardTitle class="text-3xl">
                            {{
                                props.tracks.reduce(
                                    (sum, t) => sum + t.completed_lessons_count,
                                    0,
                                )
                            }}
                            <span
                                class="text-muted-foreground text-lg font-normal"
                                >/
                                {{
                                    props.tracks.reduce(
                                        (sum, t) => sum + t.lessons_count,
                                        0,
                                    )
                                }}</span
                            >
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Fortschritt je Track') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p
                        v-if="props.tracks.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ trans('Noch keine Tracks verfügbar.') }}
                    </p>
                    <div
                        v-for="track in props.tracks"
                        :key="track.slug"
                        class="flex items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <Link
                            :href="showTrack(track.slug)"
                            class="hover:underline"
                        >
                            <span class="font-medium">{{
                                trans(track.title_key)
                            }}</span>
                        </Link>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline">
                                {{
                                    trans(':completed von :total Lektionen', {
                                        completed:
                                            track.completed_lessons_count,
                                        total: track.lessons_count,
                                    })
                                }}
                            </Badge>
                            <Badge
                                v-if="track.exam.passed"
                                variant="default"
                                :title="
                                    track.exam.passed_at
                                        ? formatDate(track.exam.passed_at)
                                        : undefined
                                "
                            >
                                {{ trans('Bestanden') }}
                            </Badge>
                            <Link
                                v-else-if="track.exam.available"
                                :href="startExam({ track: track.slug })"
                                method="post"
                                as="button"
                            >
                                <Badge
                                    variant="secondary"
                                    class="hover:bg-accent cursor-pointer"
                                >
                                    {{ trans('Prüfung verfügbar') }}
                                </Badge>
                            </Link>
                            <Badge
                                v-else-if="track.exam.defined"
                                variant="outline"
                            >
                                {{ trans('Prüfung noch nicht verfügbar') }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="props.due_reviews_count > 0">
                <CardHeader>
                    <CardTitle>{{ trans('Fällige Wiederholungen') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Link
                        :href="reviewIndex()"
                        class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3 text-sm transition-colors"
                    >
                        <span>{{
                            trans(':count Wissenskarten sind fällig', {
                                count: props.due_reviews_count,
                            })
                        }}</span>
                        <Badge variant="outline">{{
                            trans('Jetzt wiederholen')
                        }}</Badge>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Zuletzt bearbeitet') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <p
                        v-if="props.recent_lessons.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ trans('Noch keine Lektion begonnen.') }}
                    </p>
                    <Link
                        v-for="lesson in props.recent_lessons"
                        :key="lesson.lesson_id"
                        :href="showLesson(lesson.lesson_id)"
                        class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3 text-sm transition-colors"
                    >
                        <span>{{ lesson.lesson_id }} — {{ lesson.title }}</span>
                        <Badge
                            :variant="
                                lesson.status === 'completed'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{
                                lesson.status === 'completed'
                                    ? trans('Erledigt')
                                    : trans('Begonnen')
                            }}
                        </Badge>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>{{ trans('Achievements') }}</CardTitle>
                    <span class="text-muted-foreground text-sm">
                        {{ unlockedAchievementsCount }} /
                        {{ props.achievements.length }}
                    </span>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="props.achievements.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ trans('Noch keine Achievements verfügbar.') }}
                    </p>
                    <div v-else class="flex flex-wrap gap-4">
                        <AchievementBadge
                            v-for="achievement in dashboardAchievements"
                            :key="achievement.slug"
                            :achievement="achievement"
                            size="md"
                            :show-date="achievement.unlocked"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
