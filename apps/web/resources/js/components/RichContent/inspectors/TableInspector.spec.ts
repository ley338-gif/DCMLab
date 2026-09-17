import { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { fromTipTap } from '@/lib/richContent/RichContentEditorAdapter';
import { richContentExtensions } from '@/lib/richContent/tiptapExtensions';
import TableInspector from './TableInspector.vue';
import type { RichContentTableNode } from '@/types/richContent';

/**
 * `@tiptap/vue-3`'s `Editor` markRaw()s itself -- required whenever an
 * editor instance is passed through a mounted component's props, see
 * RichContentToolbox.spec.ts for the full explanation.
 */
function twoTableDoc(): Editor {
    return new Editor({
        extensions: richContentExtensions([]),
        content: {
            type: 'doc',
            content: [
                {
                    type: 'table',
                    content: [
                        {
                            type: 'tableRow',
                            content: [
                                {
                                    type: 'tableCell',
                                    content: [
                                        {
                                            type: 'paragraph',
                                            content: [
                                                { type: 'text', text: 'A1' },
                                            ],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
                { type: 'paragraph', content: [] },
                {
                    type: 'table',
                    content: [
                        {
                            type: 'tableRow',
                            content: [
                                {
                                    type: 'tableCell',
                                    content: [
                                        {
                                            type: 'paragraph',
                                            content: [
                                                { type: 'text', text: 'B1' },
                                            ],
                                        },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        },
    });
}

function tableCellTexts(editor: Editor, tableIndex: number): string[] {
    const doc = fromTipTap(editor.getJSON());
    const table = doc.content.filter(
        (node): node is RichContentTableNode => node.type === 'table',
    )[tableIndex];
    const texts: string[] = [];

    table.content.forEach((row) => {
        row.content.forEach((cell) => {
            const paragraph = cell.content[0];
            const text =
                paragraph?.type === 'paragraph' &&
                paragraph.content[0]?.type === 'text'
                    ? paragraph.content[0].text
                    : '';
            texts.push(text);
        });
    });

    return texts;
}

describe('TableInspector (real focus-transfer behavior)', () => {
    /**
     * Betreiber-Korrektur (Plan §13): "verify real TipTap behavior when
     * focus moves to the inspector and ensure operations target the
     * currently selected table safely" -- mit ZWEI Tabellen im selben
     * Dokument, Cursor in der ZWEITEN, DOM-Fokus explizit auf ein
     * Inspector-Element verschoben (simuliert per `blur`), dann eine
     * Operation ausgeloest. Die ERSTE Tabelle darf dabei NIE veraendert
     * werden.
     */
    it('adding a row after the cursor only affects the table the cursor is actually in', async () => {
        const editor = twoTableDoc();
        // Cursor in Tabelle 2 (Text "B1"): deren Zelle beginnt hinter Tabelle 1
        // (nodeSize 8) + Absatz (nodeSize 2) + Tabelle-Oeffnung.
        const secondTableStart = editor.state.doc.child(0).nodeSize + 2;
        editor.commands.setTextSelection(secondTableStart + 4);

        const wrapper = mount(TableInspector, { props: { editor } });

        // DOM-Fokus explizit weg vom Editor (wie ein echter Klick auf den
        // Inspector) -- die ProseMirror-Selektion selbst bleibt unveraendert.
        editor.view.dom.blur();

        const button = wrapper
            .findAll('button')
            .find((node) => node.text() === 'Zeile danach einfügen')!;
        await button.trigger('click');

        expect(tableCellTexts(editor, 0)).toEqual(['A1']);
        expect(tableCellTexts(editor, 1)).toEqual(['B1', '']);
    });

    it('deleting a column only affects the table the cursor is in', async () => {
        const editor = twoTableDoc();
        const secondTableStart = editor.state.doc.child(0).nodeSize + 2;
        editor.commands.setTextSelection(secondTableStart + 4);

        const wrapper = mount(TableInspector, { props: { editor } });
        editor.view.dom.blur();

        const button = wrapper
            .findAll('button')
            .find((node) => node.text() === 'Spalte danach einfügen')!;
        await button.trigger('click');

        expect(tableCellTexts(editor, 0)).toEqual(['A1']);
        expect(tableCellTexts(editor, 1)).toEqual(['B1', '']);
    });

    it('performs no operation while readonly', async () => {
        const editor = twoTableDoc();
        editor.commands.setTextSelection(3);

        const wrapper = mount(TableInspector, {
            props: { editor, readonly: true },
        });

        const buttons = wrapper.findAll('button');
        buttons.forEach((button) => {
            expect(button.attributes('disabled')).toBeDefined();
        });

        await buttons[0].trigger('click');
        expect(tableCellTexts(editor, 0)).toEqual(['A1']);
    });
});
