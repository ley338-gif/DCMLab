<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { edit as editAchievement } from '@/routes/author/achievements/edit';
import { index as reviewQueueIndex } from '@/routes/author/review-queue';
import { index as usersIndex } from '@/routes/author/users';

type AssignedActivity = {
    id: number;
    type: string;
    key: string;
    title: string;
};

const props = defineProps<{
    role: 'learner' | 'author' | 'reviewer';
    assigned_activities: AssignedActivity[];
    review_queue_count: number | null;
}>();

const roleLabels: Record<string, string> = {
    author: trans('Autor:in'),
    reviewer: trans('Reviewer:in'),
};

const editorRoutes: Record<string, (key: string) => string> = {
    // Studio-Lessons-Umbau: Studio ist jetzt der kanonische Lesson-Workflow
    // -- derselbe LessonEditorController, nur nicht mehr der
    // author/...-Alias, den es weiterhin gibt, aber niemand mehr neu
    // verlinkt.
    lesson: (key) => `/de/studio/lessons/${key}`,
    exam: (key) => `/de/author/exams/${key}/edit`,
};

const newAchievementSlug = ref('');

function goToNewAchievement() {
    if (newAchievementSlug.value.trim() === '') {
        return;
    }

    router.visit(editAchievement(newAchievementSlug.value.trim()).url);
}
</script>

<template>
    <Head :title="trans('Autoren-Panel')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: home() },
                { title: trans('Autoren-Panel'), href: '' },
            ]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold">
                {{ trans('Autoren-Panel') }}
            </h1>
            <Badge variant="outline">{{ roleLabels[props.role] }}</Badge>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card v-if="props.role === 'reviewer'">
                <CardHeader>
                    <CardTitle>{{ trans('Review-Queue') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(':count Einreichungen warten auf Freigabe.', {
                                count: props.review_queue_count ?? 0,
                            })
                        }}
                    </p>
                    <Link :href="reviewQueueIndex()">
                        <Button variant="outline">{{
                            trans('Zur Review-Queue')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card v-if="props.role === 'reviewer'">
                <CardHeader>
                    <CardTitle>{{ trans('Nutzerverwaltung') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(
                                'Rollen vergeben und Autor:innen einzelnen Inhalten zuordnen.',
                            )
                        }}
                    </p>
                    <Link :href="usersIndex()">
                        <Button variant="outline">{{
                            trans('Zur Nutzerverwaltung')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        trans('Neue Achievement anlegen')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(
                                'Slug eingeben und im Editor öffnen — neue Achievements entstehen dort direkt.',
                            )
                        }}
                    </p>
                    <div class="flex gap-2">
                        <Input
                            v-model="newAchievementSlug"
                            :placeholder="trans('z. B. neuer-meilenstein')"
                            @keyup.enter="goToNewAchievement"
                        />
                        <Button @click="goToNewAchievement">{{
                            trans('Öffnen')
                        }}</Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{
                        trans('Meine zugewiesenen Inhalte')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-2">
                    <p
                        v-if="props.assigned_activities.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{
                            trans(
                                'Dir ist noch kein Inhalt zugewiesen — das übernimmt ein Reviewer über die Nutzerverwaltung.',
                            )
                        }}
                    </p>
                    <template v-else>
                        <Link
                            v-for="activity in props.assigned_activities"
                            :key="activity.id"
                            :href="
                                editorRoutes[activity.type]?.(activity.key) ??
                                '#'
                            "
                            class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3 text-sm transition-colors"
                        >
                            <span>{{ activity.title }}</span>
                            <Badge variant="secondary">{{
                                activity.type
                            }}</Badge>
                        </Link>
                    </template>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
