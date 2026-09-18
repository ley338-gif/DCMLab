<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AchievementBadge from '@/components/achievements/AchievementBadge.vue';
import ContinueLearningCard from '@/components/dashboard/ContinueLearningCard.vue';
import DashboardLabCard from '@/components/dashboard/DashboardLabCard.vue';
import DashboardTrackCard from '@/components/dashboard/DashboardTrackCard.vue';
import RecommendedNextCard from '@/components/dashboard/RecommendedNextCard.vue';
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
import { index as tracksIndex, show as showTrack } from '@/routes/tracks';
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
    title: { de: string } | null;
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
    track_slug: string;
    track_title_key: string;
};

type ContinueLearning = {
    track_slug: string;
    track_title_key: string;
    track_title: { de: string } | null;
    lesson_id: string;
    lesson_title: string;
    position: number;
    lessons_count: number;
    completed_lessons_count: number;
} | null;

type LabOverview = {
    slug: string;
    title: string;
    difficulty: string;
    estimated_minutes: number;
    status: 'not_started' | 'in_progress' | 'solved';
};

type Recommended = {
    type: 'lab' | 'lesson' | 'track';
    reason_code:
        | 'fits_current_track'
        | 'prerequisites_met'
        | 'track_completed'
        | 'beginner_recommendation';
    title: string | null;
    title_key: string | null;
    slug: string | null;
    lesson_id: string | null;
    completed_track_title: string | null;
    completed_track_title_key: string | null;
} | null;

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
    continue_learning: ContinueLearning;
    labs: LabOverview[];
    recommended: Recommended;
}>();

const rankLabels: Record<string, string> = {
    novice: trans('Novize'),
    operator: trans('Operator'),
    administrator: trans('Administrator'),
    architect: trans('Architekt'),
    standard_bearer: trans('Standard Bearer'),
};

// "Deine Tracks": begonnene Tracks zuerst (am relevantesten), dann
// unbegonnene, abgeschlossene zuletzt -- auf 4 Karten begrenzt, der
// vollstaendige Katalog bleibt ueber "Alle Tracks ansehen" erreichbar.
const highlightedTracks = computed(() => {
    const inProgress = props.tracks.filter(
        (t) =>
            t.completed_lessons_count > 0 &&
            t.completed_lessons_count < t.lessons_count,
    );
    const notStarted = props.tracks.filter(
        (t) => t.completed_lessons_count === 0 && t.lessons_count > 0,
    );
    const completed = props.tracks.filter(
        (t) =>
            t.lessons_count > 0 && t.completed_lessons_count >= t.lessons_count,
    );

    return [...inProgress, ...notStarted, ...completed].slice(0, 4);
});

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

const completedLessonsTotal = computed(() =>
    props.tracks.reduce((sum, t) => sum + t.completed_lessons_count, 0),
);
const lessonsTotal = computed(() =>
    props.tracks.reduce((sum, t) => sum + t.lessons_count, 0),
);

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

    <PageContainer wide>
        <div class="grid min-w-0 gap-6 lg:grid-cols-[1fr_320px]">
            <!-- Hauptspalte: "Was jetzt?" zuerst -->
            <div class="flex min-w-0 flex-col gap-6">
                <ContinueLearningCard
                    :continue-learning="continue_learning"
                    :beginner-recommendation="recommended"
                />

                <section aria-labelledby="your-tracks-heading">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 id="your-tracks-heading" class="font-semibold">
                            {{ trans('Deine Tracks') }}
                        </h2>
                        <Link
                            :href="tracksIndex()"
                            class="text-sm font-medium hover:underline"
                        >
                            {{ trans('Alle Tracks ansehen') }}
                        </Link>
                    </div>
                    <p
                        v-if="highlightedTracks.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ trans('Noch keine Tracks verfügbar.') }}
                    </p>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <DashboardTrackCard
                            v-for="track in highlightedTracks"
                            :key="track.slug"
                            :track="track"
                        />
                    </div>
                </section>

                <section aria-labelledby="your-labs-heading">
                    <h2 id="your-labs-heading" class="mb-3 font-semibold">
                        {{ trans('Deine Labs') }}
                    </h2>
                    <p
                        v-if="labs.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{
                            trans(
                                'Neue Labs werden verfügbar, sobald du passende Grundlagen abgeschlossen hast.',
                            )
                        }}
                    </p>
                    <div v-else class="grid gap-3 sm:grid-cols-2">
                        <DashboardLabCard
                            v-for="lab in labs"
                            :key="lab.slug"
                            :lab="lab"
                        />
                    </div>
                </section>

                <RecommendedNextCard :recommended="recommended" />
            </div>

            <!-- Sidebar: Status, Wiederholungen, Achievements, Aktivitaet -->
            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Dein Status')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-xs">
                                {{ trans('Punkte') }}
                            </p>
                            <p class="text-xl font-semibold">
                                {{ props.profile.points }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">
                                {{ trans('Rang') }}
                            </p>
                            <p class="text-xl font-semibold">
                                {{
                                    rankLabels[props.profile.rank] ??
                                    props.profile.rank
                                }}
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-muted-foreground text-xs">
                                {{ trans('Lektionen erledigt') }}
                            </p>
                            <p class="text-xl font-semibold">
                                {{ completedLessonsTotal }}
                                <span
                                    class="text-muted-foreground text-sm font-normal"
                                    >/ {{ lessonsTotal }}</span
                                >
                            </p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-muted-foreground text-xs">
                                {{ trans('Achievements') }}
                            </p>
                            <p class="text-xl font-semibold">
                                {{ unlockedAchievementsCount }}
                                <span
                                    class="text-muted-foreground text-sm font-normal"
                                    >/ {{ props.achievements.length }}</span
                                >
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Wiederholen')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Link
                            v-if="due_reviews_count > 0"
                            :href="reviewIndex()"
                            class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3 text-sm transition-colors"
                        >
                            <span>{{
                                trans(':count Wiederholung fällig', {
                                    count: due_reviews_count,
                                })
                            }}</span>
                            <Badge variant="outline">{{
                                trans('Jetzt wiederholen')
                            }}</Badge>
                        </Link>
                        <p v-else class="text-muted-foreground text-sm">
                            {{
                                trans(
                                    'Alles aktuell – momentan ist keine Wiederholung fällig.',
                                )
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle class="text-base">{{
                            trans('Achievements')
                        }}</CardTitle>
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
                            {{ trans('Dein erstes Achievement wartet schon.') }}
                        </p>
                        <div v-else class="flex flex-wrap gap-3">
                            <AchievementBadge
                                v-for="achievement in dashboardAchievements"
                                :key="achievement.slug"
                                :achievement="achievement"
                                size="sm"
                                :show-date="achievement.unlocked"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Aktivität')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <p
                            v-if="props.recent_lessons.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            {{ trans('Noch keine Lektion begonnen.') }}
                        </p>
                        <Link
                            v-for="lesson in props.recent_lessons.slice(0, 5)"
                            :key="lesson.lesson_id"
                            :href="showLesson(lesson.lesson_id)"
                            class="hover:bg-accent/50 rounded-lg border p-3 text-sm transition-colors"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="truncate">{{ lesson.title }}</span>
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
                            </div>
                            <span class="text-muted-foreground">
                                {{ trans(lesson.track_title_key) }}
                            </span>
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            trans('Prüfungen')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <p
                            v-if="props.tracks.every((t) => !t.exam.defined)"
                            class="text-muted-foreground text-sm"
                        >
                            {{ trans('Noch keine Tracks verfügbar.') }}
                        </p>
                        <div
                            v-for="track in props.tracks.filter(
                                (t) => t.exam.defined,
                            )"
                            :key="track.slug"
                            class="flex items-center justify-between gap-2 rounded-lg border p-3 text-sm"
                        >
                            <Link
                                :href="showTrack(track.slug)"
                                class="truncate hover:underline"
                            >
                                {{ track.title?.de ?? trans(track.title_key) }}
                            </Link>
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
                            <Badge v-else variant="outline">
                                {{ trans('Prüfung noch nicht verfügbar') }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </PageContainer>
</template>
