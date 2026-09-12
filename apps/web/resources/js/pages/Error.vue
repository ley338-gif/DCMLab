<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
}>();

const messages: Record<number, { title: string; description: string }> = {
    403: {
        title: trans('403 — Kein Zugriff'),
        description: trans('Du hast keine Berechtigung, diese Seite zu sehen.'),
    },
    404: {
        title: trans('404 — Nicht gefunden'),
        description: trans('Diese Seite gibt es nicht (mehr).'),
    },
    419: {
        title: trans('419 — Sitzung abgelaufen'),
        description: trans(
            'Deine Sitzung ist abgelaufen. Bitte lade die Seite neu.',
        ),
    },
    429: {
        title: trans('429 — Zu viele Anfragen'),
        description: trans(
            'Du warst gerade etwas zu schnell. Versuch es in einer Minute erneut.',
        ),
    },
    500: {
        title: trans('500 — Serverfehler'),
        description: trans(
            'Da ist etwas schiefgegangen. Wir schauen uns das an.',
        ),
    },
    503: {
        title: trans('503 — Wartungsarbeiten'),
        description: trans(
            'DCM Lab ist kurz nicht erreichbar. Versuch es gleich noch einmal.',
        ),
    },
};

const message = computed(
    () =>
        messages[props.status] ?? {
            title: trans(':status — Fehler', { status: props.status }),
            description: trans('Da ist etwas schiefgegangen.'),
        },
);
</script>

<template>
    <Head :title="message.title" />

    <div
        class="bg-background flex min-h-screen flex-col items-center justify-center gap-6 px-6 text-center"
    >
        <Link :href="home()" class="flex items-center">
            <AppLogo />
        </Link>

        <div>
            <h1 class="text-2xl font-semibold">{{ message.title }}</h1>
            <p class="text-muted-foreground mt-2">{{ message.description }}</p>
        </div>

        <Button as-child>
            <Link :href="home()">{{ trans('Zur Startseite') }}</Link>
        </Button>
    </div>
</template>
