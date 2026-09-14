import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    type Ref,
} from 'vue';

export type TocEntry = {
    id: string;
    text: string;
    level: number;
};

/**
 * Scannt die per v-html gerenderten Headings (schon mit id="…" von
 * App\Content\MarkdownRenderer::addHeadingAnchors) im uebergebenen
 * Container und haelt per IntersectionObserver fest, welcher Abschnitt
 * gerade sichtbar ist -- wiederverwendbar fuer jede kuenftige Lesson-Seite.
 */
export function useLessonToc(
    containerRef: Ref<HTMLElement | null>,
    bodyHtml: Ref<string>,
) {
    const entries = ref<TocEntry[]>([]);
    const activeId = ref<string | null>(null);
    let observer: IntersectionObserver | null = null;
    const visibleIds = new Set<string>();

    function rebuild() {
        observer?.disconnect();
        visibleIds.clear();

        const container = containerRef.value;
        if (!container) {
            entries.value = [];
            return;
        }

        const headings = Array.from(
            container.querySelectorAll<HTMLElement>('h2[id], h3[id]'),
        );

        entries.value = headings.map((heading) => ({
            id: heading.id,
            text: heading.textContent?.trim() ?? '',
            level: heading.tagName === 'H2' ? 2 : 3,
        }));

        if (headings.length === 0) {
            return;
        }

        observer = new IntersectionObserver(
            (observedEntries) => {
                for (const entry of observedEntries) {
                    const id = (entry.target as HTMLElement).id;
                    if (entry.isIntersecting) {
                        visibleIds.add(id);
                    } else {
                        visibleIds.delete(id);
                    }
                }

                const firstVisible = headings.find((heading) =>
                    visibleIds.has(heading.id),
                );
                if (firstVisible) {
                    activeId.value = firstVisible.id;
                }
            },
            { rootMargin: '-96px 0px -70% 0px', threshold: 0 },
        );

        headings.forEach((heading) => observer?.observe(heading));
        activeId.value = headings[0]?.id ?? null;
    }

    function scrollToEntry(id: string) {
        const el = document.getElementById(id);
        if (!el) {
            return;
        }

        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        el.scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'start',
        });
        history.replaceState(null, '', `#${id}`);
        activeId.value = id;
    }

    onMounted(async () => {
        await nextTick();
        rebuild();
    });

    watch(bodyHtml, async () => {
        await nextTick();
        rebuild();
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
    });

    return {
        entries: computed(() => entries.value),
        activeId: computed(() => activeId.value),
        scrollToEntry,
    };
}
