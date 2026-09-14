import { ref } from 'vue';

/**
 * Eine Copy-Implementierung fuer CommandExample.vue *und* die per
 * MarkdownRenderer erzeugten Prosa-Bloecke (.lesson-console/.lesson-code,
 * beide ueber [data-copy] delegiert -- siehe useLessonProseEnhancements).
 * Ein gemeinsamer Ort statt zwei fetch/execCommand-Implementierungen.
 */
export function useClipboardCopy() {
    const copiedKey = ref<string | null>(null);

    async function copy(text: string, key: string = text): Promise<void> {
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

            copiedKey.value = key;
            window.setTimeout(() => {
                if (copiedKey.value === key) {
                    copiedKey.value = null;
                }
            }, 1500);
        } catch {
            // Zwischenablage kann durch Berechtigungen blockiert sein --
            // dann bleibt der Button einfach ohne Feedback, kein Fehlerdialog.
        }
    }

    return { copiedKey, copy };
}
