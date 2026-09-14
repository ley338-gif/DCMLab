<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from '@/lib/trans';
import { leaderboard } from '@/routes';
import { datenschutz, impressum, nutzungsbedingungen } from '@/routes/legal';
</script>

<template>
    <footer class="border-t">
        <div
            class="text-muted-foreground mx-auto flex max-w-5xl flex-wrap gap-4 px-6 py-6 text-sm"
        >
            <Link :href="leaderboard()">{{ trans('Leaderboard') }}</Link>
            <Link :href="impressum()">{{ trans('Impressum') }}</Link>
            <Link :href="datenschutz()">{{ trans('Datenschutz') }}</Link>
            <Link :href="nutzungsbedingungen()">{{
                trans('Nutzungsbedingungen')
            }}</Link>
        </div>
    </footer>
</template>
