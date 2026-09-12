<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
</script>

<template>
    <Head :title="trans('Nutzungsbedingungen')" />

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
                {{ trans('Nutzungsbedingungen') }}
            </h1>

            <div class="bg-muted rounded-lg border p-4 text-sm">
                {{
                    trans(
                        'Platzhalter — hier steht die vollständige rechtliche Fassung, sobald DCM Lab von einer echten Rechtsperson betrieben wird. Der folgende Hinweis zur Spielwiese gilt schon jetzt inhaltlich, unabhängig vom endgültigen Wortlaut.',
                    )
                }}
            </div>

            <section class="space-y-2 text-sm">
                <h2 class="font-medium">
                    {{ trans('Die Spielwiese führt deinen Code aus') }}
                </h2>
                <p class="text-muted-foreground">
                    {{
                        trans(
                            'Wenn du die Spielwiese startest, bekommst du eine eigene, isolierte Umgebung mit einem echten DICOM-Archiv, in der du beliebige Befehle über das Terminal ausführst. Diese Befehle laufen als dein eigener Nutzercode in Containern, die für deine Sitzung erzeugt und danach wieder gelöscht werden. Die Umgebung hat keinen Zugriff auf das Internet oder andere Systeme (Egress ist gesperrt), und es dürfen keine echten oder personenbezogenen Daten hochgeladen oder verarbeitet werden — nur die von der Plattform bereitgestellten, synthetischen Testdaten.',
                        )
                    }}
                </p>
                <p class="text-muted-foreground">
                    {{
                        trans(
                            'Ein Missbrauchsversuch (z. B. der Versuch, aus der Umgebung auszubrechen oder Ressourcen anderer Nutzer zu beeinträchtigen) kann zum Ausschluss vom Dienst führen.',
                        )
                    }}
                </p>
            </section>
        </main>
    </div>
</template>
