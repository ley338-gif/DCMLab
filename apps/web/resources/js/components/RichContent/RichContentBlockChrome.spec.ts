import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import type { RichContentDocument } from '@/types/richContent';
import RichContentEditor from './RichContentEditor.vue';

/**
 * Siehe RichContentEditor.spec.ts::flushEditor() -- dieselbe Notwendigkeit
 * gilt hier, plus ein zusaetzlicher Umlauf, weil eine NodeView-Vue-
 * Komponente (RichContentBlockChrome.vue) noch einmal asynchron eingehaengt
 * wird, nachdem ProseMirror die uebergeordnete View aufgebaut hat.
 */
async function flushEditor(): Promise<void> {
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
}

function docWith(
    block: RichContentDocument['content'][number],
): RichContentDocument {
    return { type: 'doc', version: 1, content: [block] };
}

describe('RichContentBlockChrome (NodeView wiring)', () => {
    it('renders chrome with the correct label for a callout', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'callout',
                    attrs: { kind: 'info' },
                    content: [{ type: 'paragraph', content: [] }],
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="callout"]',
        );
        expect(chrome.exists()).toBe(true);
        expect(chrome.text()).toContain('Info-Box');
    });

    it('renders chrome with the correct label for a self_check', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'self_check',
                    attrs: { summary: 'Antwort anzeigen' },
                    content: [{ type: 'paragraph', content: [] }],
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="selfCheck"]',
        );
        expect(chrome.exists()).toBe(true);
        expect(chrome.text()).toContain('Selbstcheck');
    });

    it('renders chrome with the correct label for a dicom_tag_table', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'dicom_tag_table',
                    content: [{ tag: '', keyword: '', vr: '', value: '' }],
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="dicomTagTable"]',
        );
        expect(chrome.exists()).toBe(true);
        expect(chrome.text()).toContain('DICOM Tag-Tabelle');
    });

    it('renders chrome with the variant-specific label for a code_block', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'code_block',
                    attrs: { variant: 'terminal' },
                    text: '$ ls',
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="codeBlock"]',
        );
        expect(chrome.exists()).toBe(true);
        expect(chrome.text()).toContain('Terminal');
    });

    /**
     * `NodeViewContent` rendert standardmaessig ein schlichtes `<div>` --
     * ohne eine `<table>/<tbody>`-Huelle stuenden die `<tr>`-Kindknoten
     * ausserhalb jeden Tabellen-Kontexts im DOM (ungueltiges HTML). Regression
     * fuer genau diesen Fehler, gefunden bei der Live-Verifikation gegen eine
     * echte Lektion.
     */
    it('wraps a dicom_tag_table NodeView in a valid table/tbody structure', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'dicom_tag_table',
                    content: [{ tag: 'A', keyword: '', vr: '', value: '' }],
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="dicomTagTable"]',
        );
        const table = chrome.find('table.dicom-tag-table');
        expect(table.exists()).toBe(true);
        const tbody = table.find('tbody');
        expect(tbody.exists()).toBe(true);
        expect(tbody.find('tr[data-dicom-tag-row]').exists()).toBe(true);
    });

    /**
     * Ohne `<pre><code>` verliert der Code-Block seine Monospace-Darstellung
     * im Editor (Whitespace-Erhalt uebernimmt ProseMirror unabhaengig davon
     * bereits selbst ueber `whitespace: 'pre'`).
     */
    it('wraps a code_block NodeView in pre/code for monospace rendering', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'code_block',
                    attrs: { variant: 'terminal' },
                    text: '$ ls',
                }),
            },
        });
        await flushEditor();

        const chrome = wrapper.find(
            '.rich-content-block-chrome[data-block-type="codeBlock"]',
        );
        const pre = chrome.find('pre');
        expect(pre.exists()).toBe(true);
        expect(pre.find('code').exists()).toBe(true);
        expect(pre.text()).toContain('$ ls');
    });

    it('never wraps a table in chrome (Table keeps its own TipTap NodeView unchanged)', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'table',
                    content: [
                        {
                            type: 'table_row',
                            content: [
                                {
                                    type: 'table_cell',
                                    attrs: { header: false },
                                    content: [
                                        { type: 'paragraph', content: [] },
                                    ],
                                },
                            ],
                        },
                    ],
                }),
            },
        });
        await flushEditor();

        expect(
            wrapper
                .find('.rich-content-block-chrome[data-block-type="table"]')
                .exists(),
        ).toBe(false);
        expect(wrapper.find('table').exists()).toBe(true);
    });

    /**
     * Architektonische Garantie (Plan §14): die Chrome-DOM darf NIE im
     * serialisierten Dokument auftauchen -- `getJSON()`/`fromTipTap()` lesen
     * ausschliesslich den ProseMirror-Knoten, nie die NodeView-Wrapper-DOM.
     */
    it('never leaks chrome markup into the emitted document', async () => {
        const wrapper = mount(RichContentEditor, {
            props: {
                modelValue: docWith({
                    type: 'callout',
                    attrs: { kind: 'warning' },
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Hi' }],
                        },
                    ],
                }),
            },
        });
        await flushEditor();

        const editor = (
            wrapper.vm as unknown as {
                editor: { getJSON: () => unknown };
            }
        ).editor;

        expect(JSON.stringify(editor.getJSON())).not.toContain(
            'rich-content-block-chrome',
        );
    });
});
