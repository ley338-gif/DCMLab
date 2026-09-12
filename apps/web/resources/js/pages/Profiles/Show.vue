<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { exportMethod as exportProfile } from '@/routes/profiles';
import { computed } from 'vue';

type FirstBlood = {
    node_title: string;
    awarded_at: string;
};

type ProfileData = {
    name: string;
    rank: string;
    points: number;
    skill_vector: Record<string, number>;
    member_since: string | null;
    first_bloods: FirstBlood[];
};

const props = defineProps<{
    profile: ProfileData;
    slug: string;
}>();

// Abschnitt 7: immer alle fuenf Kategorien in fester Reihenfolge, damit das
// Radar Luecken zeigt statt sie zu verschweigen.
const SKILL_CATEGORIES = [
    'netzwerk',
    'datenmodell',
    'bildgebung',
    'integration',
    'security',
] as const;

// Genug Rand um das Polygon, damit die Kategorie-Labels nicht am SVG-Rand
// abgeschnitten werden (viewBox clippt Inhalt ausserhalb seiner Grenzen).
const RADAR_RADIUS = 70;
const RADAR_CENTER = 130;
const RADAR_SIZE = RADAR_CENTER * 2;

const maxSkillValue = computed(() =>
    Math.max(
        1,
        ...SKILL_CATEGORIES.map((key) => props.profile.skill_vector[key] ?? 0),
    ),
);

function pointOnAxis(
    index: number,
    fraction: number,
): { x: number; y: number } {
    const angle = (Math.PI * 2 * index) / SKILL_CATEGORIES.length - Math.PI / 2;
    return {
        x: RADAR_CENTER + Math.cos(angle) * RADAR_RADIUS * fraction,
        y: RADAR_CENTER + Math.sin(angle) * RADAR_RADIUS * fraction,
    };
}

const axisLines = computed(() =>
    SKILL_CATEGORIES.map((_, index) => pointOnAxis(index, 1)),
);

const labelPoints = computed(() =>
    SKILL_CATEGORIES.map((key, index) => ({
        key,
        ...pointOnAxis(index, 1.22),
    })),
);

const gridRings = [0.25, 0.5, 0.75, 1];

const skillPolygonPoints = computed(() =>
    SKILL_CATEGORIES.map((key, index) => {
        const value = props.profile.skill_vector[key] ?? 0;
        const fraction = value / maxSkillValue.value;
        const point = pointOnAxis(index, fraction);
        return `${point.x},${point.y}`;
    }).join(' '),
);

function ringPoints(fraction: number): string {
    return SKILL_CATEGORIES.map((_, index) => {
        const point = pointOnAxis(index, fraction);
        return `${point.x},${point.y}`;
    }).join(' ');
}
</script>

<template>
    <Head :title="profile.name" />

    <div class="bg-background min-h-screen">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-6 py-4"
            >
                <Link :href="home()" class="flex items-center">
                    <AppLogo />
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div class="mb-6 flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ profile.name }}</h1>
                    <p
                        v-if="profile.member_since"
                        class="text-muted-foreground text-sm"
                    >
                        {{
                            trans('Member since :date', {
                                date: profile.member_since,
                            })
                        }}
                    </p>
                </div>
                <Button as-child variant="outline">
                    <a :href="exportProfile(props.slug).url">
                        {{ trans('Download as PDF') }}
                    </a>
                </Button>
            </div>

            <div class="mb-6 flex items-center gap-3">
                <Badge variant="default" class="text-sm">
                    {{ trans(`rank.${profile.rank}`) }}
                </Badge>
                <span class="text-muted-foreground text-sm">
                    {{ profile.points }} {{ trans('Points') }}
                </span>
            </div>

            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>{{ trans('Skill radar') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex justify-center">
                    <svg
                        :viewBox="`0 0 ${RADAR_SIZE} ${RADAR_SIZE}`"
                        :width="RADAR_SIZE"
                        :height="RADAR_SIZE"
                    >
                        <polygon
                            v-for="ring in gridRings"
                            :key="ring"
                            :points="ringPoints(ring)"
                            fill="none"
                            stroke="currentColor"
                            class="text-border"
                            stroke-width="1"
                        />
                        <line
                            v-for="(axis, index) in axisLines"
                            :key="index"
                            :x1="RADAR_CENTER"
                            :y1="RADAR_CENTER"
                            :x2="axis.x"
                            :y2="axis.y"
                            stroke="currentColor"
                            class="text-border"
                            stroke-width="1"
                        />
                        <polygon
                            :points="skillPolygonPoints"
                            fill="currentColor"
                            class="text-primary/30"
                            stroke="currentColor"
                            stroke-width="2"
                        />
                        <text
                            v-for="label in labelPoints"
                            :key="label.key"
                            :x="label.x"
                            :y="label.y"
                            text-anchor="middle"
                            dominant-baseline="middle"
                            class="fill-foreground text-[11px]"
                        >
                            {{ trans(`skill.${label.key}`) }}
                        </text>
                    </svg>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('First Blood') }}</CardTitle>
                    <CardDescription v-if="profile.first_bloods.length === 0">
                        {{ trans('No first bloods yet.') }}
                    </CardDescription>
                </CardHeader>
                <CardContent
                    v-if="profile.first_bloods.length > 0"
                    class="space-y-2"
                >
                    <div
                        v-for="entry in profile.first_bloods"
                        :key="entry.node_title + entry.awarded_at"
                        class="flex items-center justify-between text-sm"
                    >
                        <span>{{ entry.node_title }}</span>
                        <span class="text-muted-foreground">{{
                            entry.awarded_at
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </main>
    </div>
</template>
