<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
</script>

<template>
    <Head :title="trans('Datenschutzerklärung')" />

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

        <main class="mx-auto max-w-3xl space-y-4 px-6 py-10">
            <h1 class="text-2xl font-semibold">
                {{ trans('Datenschutzerklärung') }}
            </h1>

            <div class="bg-muted rounded-lg border p-4 text-sm">
                {{
                    trans(
                        'Platzhalter — hier steht die vollständige Datenschutzerklärung (Verantwortlicher, Zwecke und Rechtsgrundlagen der Verarbeitung, Speicherdauer, Betroffenenrechte, Auftragsverarbeiter), sobald DCM Lab von einer echten Rechtsperson betrieben wird.',
                    )
                }}
            </div>

            <section class="space-y-2 text-sm">
                <h2 class="font-medium">
                    {{ trans('Was diese Plattform speichert') }}
                </h2>
                <p class="text-muted-foreground">
                    {{
                        trans(
                            'Kontodaten (Name, E-Mail-Adresse), Lernfortschritt (gelöste Lektionen und Nodes, Punkte, Rang) sowie technische Protokolle für den Betrieb (siehe docs/betrieb.md im Quellcode). Keine echten Patientendaten — die Übungsdaten in Lektionen und der Spielwiese sind ausschließlich synthetisch erzeugt (Abschnitt 1 des Projektauftrags).',
                        )
                    }}
                </p>
            </section>
        </main>
    </div>
</template>
