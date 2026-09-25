<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { trans } from '@/lib/trans';
import { home } from '@/routes';
</script>

<template>
    <Head :title="trans('Impressum')" />

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
            <h1 class="text-2xl font-semibold">{{ trans('Impressum') }}</h1>

            <div class="bg-muted rounded-lg border p-4 text-sm">
                {{
                    trans(
                        'Platzhalter — hier stehen die Angaben nach § 5 DDG (Betreiber, Anschrift, Kontakt, ggf. Registereintrag), sobald DCM Lab von einer echten Rechtsperson betrieben wird.',
                    )
                }}
            </div>

            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Betreiber') }}
                    </dt>
                    <dd>[Platzhalter — Name/Firma]</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Anschrift') }}
                    </dt>
                    <dd>[Platzhalter — Straße, PLZ, Ort]</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ trans('Kontakt') }}
                    </dt>
                    <dd>[Platzhalter — E-Mail]</dd>
                </div>
            </dl>
        </main>
    </div>
</template>
