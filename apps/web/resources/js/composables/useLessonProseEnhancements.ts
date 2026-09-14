import { onBeforeUnmount, onMounted, type Ref } from 'vue';
import { trans } from '@/lib/trans';

/**
 * Der Lektionstext kommt weiterhin als rohes HTML aus MarkdownRenderer
 * (v-html, siehe docs/content-schema.md) -- die Copy-Buttons darin
 * (.lesson-copy-btn[data-copy], erzeugt von
 * App\Content\MarkdownRenderer::copyButton) bekommen ihr Klickverhalten
 * deshalb per Event-Delegation statt per Vue-Komponente.
 */
export function useLessonProseEnhancements(
    containerRef: Ref<HTMLElement | null>,
) {
    let resetTimer: ReturnType<typeof setTimeout> | null = null;

    async function handleClick(event: MouseEvent) {
        const target = (
            event.target as HTMLElement | null
        )?.closest<HTMLElement>('.lesson-copy-btn[data-copy]');

        if (!target) {
            return;
        }

        const text = target.dataset.copy ?? '';
        const label = target.querySelector('.lesson-copy-btn-label');
        const originalLabel = label?.textContent ?? '';

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            } else {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            }

            target.classList.add('is-copied');
            if (label) {
                label.textContent = trans('Kopiert');
            }

            if (resetTimer) {
                clearTimeout(resetTimer);
            }
            resetTimer = setTimeout(() => {
                target.classList.remove('is-copied');
                if (label) {
                    label.textContent = originalLabel;
                }
            }, 1500);
        } catch {
            // Kein Zugriff auf die Zwischenablage -- Button bleibt unveraendert.
        }
    }

    onMounted(() => {
        containerRef.value?.addEventListener('click', handleClick);
    });

    onBeforeUnmount(() => {
        containerRef.value?.removeEventListener('click', handleClick);
        if (resetTimer) {
            clearTimeout(resetTimer);
        }
    });
}
