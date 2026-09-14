<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Input } from '@/components/ui/input';
import { trans } from '@/lib/trans';
import { dashboard, leaderboard, login, register } from '@/routes';
import { impressum, datenschutz, nutzungsbedingungen } from '@/routes/legal';
import { show as showLesson } from '@/routes/lessons';
import { index as glossaryIndex } from '@/routes/glossary';
import { index as nodesIndex } from '@/routes/nodes';

type GlossaryTerm = {
    slug: string;
    term: string;
    expansion: string | null;
    short: string;
    see_also: { slug: string; term: string }[];
    lesson: { lesson_id: string; title: string } | null;
};

const props = defineProps<{ terms: GlossaryTerm[] }>();

const page = usePage();
const query = ref('');

const filteredTerms = computed(() => {
    const needle = query.value.trim().toLowerCase();

    if (needle === '') {
        return props.terms;
    }

    return props.terms.filter(
        (term) =>
            term.term.toLowerCase().includes(needle) ||
            term.expansion?.toLowerCase().includes(needle) ||
            term.short.toLowerCase().includes(needle),
    );
});

const groups = computed(() => {
    const byLetter = new Map<string, GlossaryTerm[]>();

    for (const term of filteredTerms.value) {
        const letter = term.term.charAt(0).toUpperCase();
        if (!byLetter.has(letter)) {
            byLetter.set(letter, []);
        }
        byLetter.get(letter)!.push(term);
    }

    return Array.from(byLetter.entries()).sort(([a], [b]) =>
        a.localeCompare(b),
    );
});

function scrollToTerm(slug: string) {
    document.getElementById(`term-${slug}`)?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
}
</script>

<template>
    <Head :title="trans('Glossar')" />

    <div class="bg-background min-h-screen">
        <header class="border-b">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4"
            >
                <Link :href="dashboard()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-4 text-sm">
                    <template v-if="page.props.auth.user">
                        <Link :href="dashboard()">{{
                            trans('Dashboard')
                        }}</Link>
                    </template>
                    <template v-else>
                        <Link :href="login()">{{ trans('Log in') }}</Link>
                        <Link
                            :href="register()"
                            class="text-primary font-medium"
                            >{{ trans('Register') }}</Link
                        >
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <h1 class="mb-2 text-2xl font-semibold">{{ trans('Glossar') }}</h1>
            <p class="text-muted-foreground mb-6">
                {{
                    trans(
                        'Fachbegriffe aus DICOM und PACS, kurz erklärt — dieselben Begriffe, die dir als Tooltip in den Lektionen begegnen.',
                    )
                }}
            </p>

            <div class="relative mb-8">
                <Search
                    class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
                <Input
                    v-model="query"
                    type="search"
                    class="pl-9"
                    :placeholder="trans('Begriff suchen …')"
                />
            </div>

            <p
                v-if="filteredTerms.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Kein Begriff gefunden.') }}
            </p>

            <div
                v-for="[letter, letterTerms] in groups"
                :key="letter"
                class="mb-8"
            >
                <h2
                    class="text-muted-foreground mb-3 text-sm font-semibold tracking-wide uppercase"
                >
                    {{ letter }}
                </h2>

                <div class="space-y-6">
                    <article
                        v-for="term in letterTerms"
                        :id="`term-${term.slug}`"
                        :key="term.slug"
                        class="scroll-mt-20"
                    >
                        <h3 class="font-medium">
                            {{ term.term }}
                            <span
                                v-if="
                                    term.expansion &&
                                    term.expansion !== term.term
                                "
                                class="text-muted-foreground font-normal"
                            >
                                ({{ term.expansion }})
                            </span>
                        </h3>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ term.short }}
                        </p>

                        <p
                            v-if="term.see_also.length || term.lesson"
                            class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs"
                        >
                            <span v-if="term.see_also.length">
                                {{ trans('Siehe auch') }}:
                                <template
                                    v-for="(related, index) in term.see_also"
                                    :key="related.slug"
                                >
                                    <span v-if="index > 0">, </span>
                                    <button
                                        type="button"
                                        class="underline-offset-2 hover:underline"
                                        @click="scrollToTerm(related.slug)"
                                    >
                                        {{ related.term }}
                                    </button>
                                </template>
                            </span>
                            <Link
                                v-if="term.lesson"
                                :href="showLesson(term.lesson.lesson_id)"
                                class="text-muted-foreground underline-offset-2 hover:underline"
                            >
                                {{
                                    trans('Erklärt in Lektion :lesson', {
                                        lesson: term.lesson.title,
                                    })
                                }}
                            </Link>
                        </p>
                    </article>
                </div>
            </div>
        </main>

        <footer class="border-t">
            <div
                class="text-muted-foreground mx-auto flex max-w-5xl flex-wrap gap-4 px-6 py-6 text-sm"
            >
                <Link :href="leaderboard()">{{ trans('Leaderboard') }}</Link>
                <Link :href="nodesIndex()">{{ trans('Labs') }}</Link>
                <Link :href="glossaryIndex()">{{ trans('Glossar') }}</Link>
                <Link :href="impressum()">{{ trans('Impressum') }}</Link>
                <Link :href="datenschutz()">{{ trans('Datenschutz') }}</Link>
                <Link :href="nutzungsbedingungen()">{{
                    trans('Nutzungsbedingungen')
                }}</Link>
            </div>
        </footer>
    </div>
</template>
