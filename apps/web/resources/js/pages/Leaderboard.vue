<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { show as showProfile } from '@/routes/profiles';

type Entry = {
    name: string;
    rank: string;
    points: number;
    public_slug: string;
};

defineProps<{
    entries: Entry[];
}>();
</script>

<template>
    <Head :title="trans('Leaderboard')" />

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
            <h1 class="mb-2 text-2xl font-semibold">
                {{ trans('Leaderboard') }}
            </h1>
            <p class="text-muted-foreground mb-8">
                {{ trans('The best DCM Lab operators, ranked by points') }}
            </p>

            <p
                v-if="entries.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('No one has joined the leaderboard yet.') }}
            </p>

            <ol v-else class="divide-y rounded-lg border">
                <li
                    v-for="(entry, index) in entries"
                    :key="entry.public_slug"
                    class="flex items-center justify-between gap-4 px-4 py-3"
                >
                    <div class="flex items-center gap-4">
                        <span
                            class="text-muted-foreground w-6 text-right text-sm"
                        >
                            {{ index + 1 }}
                        </span>
                        <Link
                            :href="showProfile(entry.public_slug)"
                            class="font-medium underline decoration-neutral-300 underline-offset-4 hover:decoration-current"
                        >
                            {{ entry.name }}
                        </Link>
                        <Badge variant="secondary">{{
                            trans(`rank.${entry.rank}`)
                        }}</Badge>
                    </div>
                    <span class="text-sm font-medium">
                        {{ entry.points }} {{ trans('Points') }}
                    </span>
                </li>
            </ol>
        </main>
    </div>
</template>
