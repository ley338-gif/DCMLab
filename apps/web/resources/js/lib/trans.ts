import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const translations = computed(
    () =>
        (page.props?.translations as Record<string, string> | undefined) ?? {},
);

/**
 * Loest einen Text gegen lang/<locale>.json auf (Abschnitt 8: keine
 * hartkodierten UI-Strings). Der Aufrufparameter ist zugleich der
 * Nachschlage-Key und der Fallback, falls die Uebersetzung fehlt --
 * dasselbe Prinzip wie Laravels __() fuer JSON-Sprachdateien.
 */
export function trans(
    key: string,
    replace: Record<string, string | number> = {},
): string {
    let value = translations.value[key] ?? key;

    for (const [search, val] of Object.entries(replace)) {
        value = value.replaceAll(`:${search}`, String(val));
    }

    return value;
}
