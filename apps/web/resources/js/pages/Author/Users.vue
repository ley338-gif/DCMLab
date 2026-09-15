<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { index as authorIndex } from '@/routes/author';
import { update as updateUser } from '@/routes/author/users';
import {
    destroy as destroyActivity,
    store as storeActivity,
} from '@/routes/author/users/activities';

type Role = 'learner' | 'author' | 'reviewer';

type AuthoredActivity = {
    id: number;
    type: string;
    key: string;
};

type PanelUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    authored_activities: AuthoredActivity[];
};

type Activity = {
    id: number;
    type: string;
    key: string;
};

const props = defineProps<{
    users: PanelUser[];
    activities: Activity[];
}>();

const roleLabels: Record<Role, string> = {
    learner: trans('Lernende:r'),
    author: trans('Autor:in'),
    reviewer: trans('Reviewer:in'),
};

const expanded = ref<number | null>(null);
const newActivityId = ref<Record<number, string>>({});

function toggleExpanded(userId: number) {
    expanded.value = expanded.value === userId ? null : userId;
}

function updateRole(userId: number, role: Role) {
    router.patch(
        updateUser.url({ user: userId }),
        { role },
        { preserveScroll: true },
    );
}

function assignActivity(userId: number) {
    const activityId = newActivityId.value[userId];

    if (!activityId) {
        return;
    }

    router.post(
        storeActivity.url({ user: userId }),
        { activity_id: activityId },
        {
            preserveScroll: true,
            onSuccess: () => {
                newActivityId.value[userId] = '';
            },
        },
    );
}

function removeActivity(userId: number, activityId: number) {
    router.delete(destroyActivity.url({ user: userId, activity: activityId }), {
        preserveScroll: true,
    });
}

function availableActivities(user: PanelUser): Activity[] {
    const assignedIds = new Set(
        user.authored_activities.map((activity) => activity.id),
    );

    return props.activities.filter((activity) => !assignedIds.has(activity.id));
}

const sortedUsers = computed(() =>
    [...props.users].sort((a, b) => a.name.localeCompare(b.name)),
);
</script>

<template>
    <Head :title="trans('Nutzerverwaltung')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: home() },
                { title: trans('Autoren-Panel'), href: authorIndex() },
                { title: trans('Nutzerverwaltung'), href: '' },
            ]"
        />

        <h1 class="mb-6 text-2xl font-semibold">
            {{ trans('Nutzerverwaltung') }}
        </h1>

        <div class="flex flex-col gap-3">
            <Card v-for="user in sortedUsers" :key="user.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{ user.name }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ user.email }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select
                            :value="user.role"
                            class="border-input bg-background flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs"
                            @change="
                                updateRole(
                                    user.id,
                                    ($event.target as HTMLSelectElement)
                                        .value as Role,
                                )
                            "
                        >
                            <option
                                v-for="(label, role) in roleLabels"
                                :key="role"
                                :value="role"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <Button
                            v-if="user.role === 'author'"
                            variant="outline"
                            size="sm"
                            @click="toggleExpanded(user.id)"
                        >
                            {{
                                expanded === user.id
                                    ? trans('Schließen')
                                    : trans('Inhalte zuweisen')
                            }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent
                    v-if="user.role === 'author' && expanded === user.id"
                    class="flex flex-col gap-3"
                >
                    <p
                        v-if="user.authored_activities.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        {{ trans('Noch keine Inhalte zugewiesen.') }}
                    </p>
                    <div
                        v-for="activity in user.authored_activities"
                        :key="activity.id"
                        class="flex items-center justify-between rounded-lg border p-2 text-sm"
                    >
                        <span>{{ activity.type }} — {{ activity.key }}</span>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="removeActivity(user.id, activity.id)"
                        >
                            {{ trans('Entfernen') }}
                        </Button>
                    </div>
                    <div class="flex items-end gap-2">
                        <div class="flex-1 space-y-1.5">
                            <Label>{{ trans('Neue Zuweisung') }}</Label>
                            <select
                                v-model="newActivityId[user.id]"
                                class="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs"
                            >
                                <option value="">
                                    {{ trans('Aktivität wählen…') }}
                                </option>
                                <option
                                    v-for="activity in availableActivities(
                                        user,
                                    )"
                                    :key="activity.id"
                                    :value="activity.id"
                                >
                                    {{ activity.type }} — {{ activity.key }}
                                </option>
                            </select>
                        </div>
                        <Button @click="assignActivity(user.id)">{{
                            trans('Zuweisen')
                        }}</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
