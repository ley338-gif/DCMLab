import { h } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Edit from './Edit.vue';

// Head/Link brauchen einen echten Inertia-App-Kontext, den ein isolierter
// Komponententest nicht aufbaut -- durch einfache Platzhalter ersetzt, wie
// bereits in Nodes/Show.spec.ts und Labs/Show.spec.ts.
vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    Head: { render: () => null },
    Link: {
        props: ['href'],
        render(this: { $slots: Record<string, () => unknown>; href: unknown }) {
            const href =
                typeof this.href === 'string'
                    ? this.href
                    : (this.href as { url: string }).url;

            return h('a', { href }, this.$slots.default?.() as never);
        },
    },
}));

type VersionRow = {
    id: number;
    status: string;
    is_current: boolean;
    author_name: string | null;
    published_at: string | null;
};

const baseProps = {
    lesson: { lesson_id: '4.1', title: 'Association rejected' },
    fields: {
        title: 'Association rejected',
        teaser: 'Teaser',
        level: 'aufbau' as const,
        duration_minutes: 18,
        tools: ['echoscu'],
        requires: ['1.5', '1.6'],
        glossary_terms: ['called-ae-title'],
        objectives: ['Ziel eins'],
        sandbox: { required: true, dataset: 'ct-thorax-60', note: null },
        related_node: { node: 'silent-ct', optional: false },
        rich_content: { type: 'doc', version: 1, content: [] },
    },
    catalog: {
        tools: ['echoscu'],
        glossary_terms: ['called-ae-title'],
        glossary: [],
        datasets: ['ct-thorax-60'],
        nodes: ['silent-ct'],
    },
    pending_version: null,
    versions: [] as VersionRow[],
    can_publish: true,
    preview_url: '/de/studio/lessons/4.1/preview',
};

function mountEdit(propOverrides: Record<string, unknown> = {}) {
    return mount(Edit, {
        props: { ...baseProps, ...propOverrides },
        global: { stubs: { RichContentWorkbench: true } },
    });
}

function versionRow(
    wrapper: ReturnType<typeof mountEdit>,
    id: number,
): HTMLElement {
    const row = wrapper
        .findAll('.text-sm')
        .map((el) => el.element)
        .find((el) => el.textContent?.includes(`v${id}`));

    if (!row) throw new Error(`Versionszeile v${id} nicht gefunden`);

    return row as HTMLElement;
}

/**
 * Betreiber-Befund nach PR #178 (Lesson 4.1, ContentVersion #22/#23/#24):
 * der Lesson-Editor zeigte nach einer Freigabe ueber die UI nirgends an,
 * welche ContentVersion gerade aktiv ist. Diese Tests fixieren das
 * sichtbare Verhalten der neuen "Versionen"-Karte anhand einer
 * realistischen Lesson-4.1-Konstellation (#24 veroeffentlicht/aktiv,
 * #22/#23 ersetzt).
 */
describe('Studio/Lessons/Edit Versionshistorie', () => {
    const lesson41Versions: VersionRow[] = [
        {
            id: 24,
            status: 'published',
            is_current: true,
            author_name: 'Herr Dr. Enrico Raab',
            published_at: '2026-09-22T08:41:45+00:00',
        },
        {
            id: 23,
            status: 'superseded',
            is_current: false,
            author_name: 'Herr Dr. Enrico Raab',
            published_at: null,
        },
        {
            id: 22,
            status: 'superseded',
            is_current: false,
            author_name: 'Herr Dr. Enrico Raab',
            published_at: null,
        },
    ];

    it('rendert die Überschrift "Versionen"', () => {
        const wrapper = mountEdit({ versions: lesson41Versions });

        expect(wrapper.text()).toContain('Versionen');
    });

    it('zeigt die aktuelle veröffentlichte Version mit ID, Status und Aktiv-Badge', () => {
        const wrapper = mountEdit({ versions: lesson41Versions });

        const row = versionRow(wrapper, 24);
        expect(row.textContent).toContain('v24');
        expect(row.textContent).toContain('Veröffentlicht');
        expect(row.textContent).toContain('aktiv');
    });

    it('zeigt eine ersetzte Version mit Status "Ersetzt" und ohne Aktiv-Badge', () => {
        const wrapper = mountEdit({ versions: lesson41Versions });

        const row23 = versionRow(wrapper, 23);
        expect(row23.textContent).toContain('Ersetzt');
        expect(row23.textContent).not.toContain('aktiv');

        const row22 = versionRow(wrapper, 22);
        expect(row22.textContent).toContain('Ersetzt');
        expect(row22.textContent).not.toContain('aktiv');
    });

    it('zeigt das Aktiv-Badge ausschließlich bei is_current=true', () => {
        const wrapper = mountEdit({ versions: lesson41Versions });

        const activeBadges = wrapper
            .findAll('[data-slot="badge"]')
            .filter((el) => el.text() === 'aktiv');

        expect(activeBadges).toHaveLength(1);
        expect(versionRow(wrapper, 24).textContent).toContain('aktiv');
    });

    it('zeigt den Leerzustand "Noch keine Versionshistorie." bei leerer Liste', () => {
        const wrapper = mountEdit({ versions: [] });

        expect(wrapper.text()).toContain('Noch keine Versionshistorie.');
    });

    it('stellt ein vorhandenes pending_version unabhängig von der Versionshistorie dar, ohne eine superseded-Version fälschlich als Pending zu behandeln', () => {
        // Realistische Konstellation: ein NEUER Entwurf ist in Review, waehrend
        // die alte, ueberholte Version (23) nur noch in der Historie als
        // "Ersetzt" auftaucht -- die beiden Anzeigen duerfen sich nicht
        // vermischen.
        const wrapper = mountEdit({
            pending_version: { id: 25, status: 'review' },
            versions: [
                {
                    id: 25,
                    status: 'review',
                    is_current: false,
                    author_name: 'Herr Dr. Enrico Raab',
                    published_at: null,
                },
                {
                    id: 23,
                    status: 'superseded',
                    is_current: false,
                    author_name: 'Herr Dr. Enrico Raab',
                    published_at: null,
                },
            ],
        });

        // Der Status-Badge im Kopfbereich (pending_version) zeigt "Zur Prüfung
        // eingereicht" fuer #25 -- unabhaengig davon steht #23 in der
        // Versionshistorie korrekt als "Ersetzt", nicht als eingereichter
        // Pending-Entwurf.
        expect(wrapper.text()).toContain('Zur Prüfung eingereicht');
        expect(versionRow(wrapper, 23).textContent).toContain('Ersetzt');
        expect(versionRow(wrapper, 23).textContent).not.toContain(
            'Zur Prüfung eingereicht',
        );
    });
});
