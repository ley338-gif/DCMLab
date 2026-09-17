import { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import { markRaw } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { resolveSelectedBlock } from '@/lib/richContent/insertionAnchor';
import { fromTipTap } from '@/lib/richContent/RichContentEditorAdapter';
import { richContentExtensions } from '@/lib/richContent/tiptapExtensions';
import DicomTagTableInspector from './DicomTagTableInspector.vue';
import type { RichContentDicomTagTableNode } from '@/types/richContent';

function dicomTagTable(editor: Editor): RichContentDicomTagTableNode {
    const doc = fromTipTap(editor.getJSON());
    return doc.content.find(
        (node): node is RichContentDicomTagTableNode =>
            node.type === 'dicom_tag_table',
    )!;
}

function editorWithRows(
    rows: { tag: string; keyword: string; vr: string; value: string }[],
): Editor {
    return new Editor({
        extensions: richContentExtensions([]),
        content: {
            type: 'doc',
            content: [
                {
                    type: 'dicomTagTable',
                    content: rows.map((attrs) => ({
                        type: 'dicomTagRow',
                        attrs,
                    })),
                },
            ],
        },
    });
}

/**
 * `markRaw(block.node)`: mirrors RichContentWorkbench.vue's own
 * `recomputeSelectedBlock()` -- a ProseMirror node passed unmarked through
 * Vue props gets reactively proxied, which breaks its identity-sensitive
 * `isLeaf`/`nodeSize` getters. Without this, `nodeSize` reads 2 instead of
 * 1 for these leaf `dicomTagRow` atoms, corrupting every row-offset
 * computed from it.
 */
function mountFor(editor: Editor) {
    const block = resolveSelectedBlock(editor)!;
    return mount(DicomTagTableInspector, {
        props: { editor, node: markRaw(block.node) },
    });
}

describe('DicomTagTableInspector', () => {
    it('renders one row of inputs per dicomTagRow', () => {
        const editor = editorWithRows([
            {
                tag: '(0010,0010)',
                keyword: 'PatientName',
                vr: 'PN',
                value: 'DOE',
            },
            { tag: '', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        const rows = wrapper.findAll('.rich-content-inspector-dicom-row');
        expect(rows).toHaveLength(2);
        const tagInput = rows[0].find('input[placeholder="Tag"]')
            .element as HTMLInputElement;
        expect(tagInput.value).toBe('(0010,0010)');
    });

    it('edits a single field on a specific row without touching the others', async () => {
        const editor = editorWithRows([
            { tag: 'A', keyword: '', vr: '', value: '' },
            { tag: 'B', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        const secondRowTagInput = wrapper
            .findAll('.rich-content-inspector-dicom-row')[1]
            .find('input[placeholder="Tag"]');
        await secondRowTagInput.setValue('B-edited');

        const table = dicomTagTable(editor);
        expect(table.content[0].tag).toBe('A');
        expect(table.content[1].tag).toBe('B-edited');
    });

    it('adds a new empty row at the end', async () => {
        const editor = editorWithRows([
            { tag: 'A', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        await wrapper.find('.rich-content-inspector-row-add').trigger('click');

        const table = dicomTagTable(editor);
        expect(table.content).toHaveLength(2);
        expect(table.content[1]).toEqual({
            tag: '',
            keyword: '',
            vr: '',
            value: '',
        });
    });

    it('removes a row, keeping the others intact', async () => {
        const editor = editorWithRows([
            { tag: 'A', keyword: '', vr: '', value: '' },
            { tag: 'B', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        const rows = wrapper.findAll('.rich-content-inspector-dicom-row');
        await rows[0]
            .find('.rich-content-inspector-row-remove')
            .trigger('click');

        const table = dicomTagTable(editor);
        expect(table.content).toHaveLength(1);
        expect(table.content[0].tag).toBe('B');
    });

    /**
     * Betreiber-Befund aus der PR-Pruefung: `updateRow()` wird bei JEDEM
     * Tastendruck in einer Zellen-Eingabe aufgerufen. Ein `.focus()` im
     * Chain wuerde den DOM-Fokus vom gerade getippten Feld auf den Editor
     * zurueckreissen und fortlaufendes Tippen unmoeglich machen. Simuliert
     * mehrere aufeinanderfolgende Tastendruecke und prueft direkt, ob
     * TipTaps `focus`-Kommando (`view.focus()`) ausgeloest wird -- empirisch
     * verifiziert: mit `.focus()` im Chain schlaegt genau diese Testform 5x
     * fehl, einmal je simuliertem Tastendruck.
     */
    it("never triggers TipTap's focus command while typing continuously in a row field", async () => {
        const editor = editorWithRows([
            { tag: '', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        const focusSpy = vi.spyOn(editor.view, 'focus');
        const tagInput = wrapper.find('input[placeholder="Tag"]');

        for (const value of ['(', '(0', '(00', '(001', '(0010']) {
            await tagInput.setValue(value);
        }

        // TipTaps `focus`-Kommando ruft `view.focus()` verzoegert ueber
        // `requestAnimationFrame` auf (siehe @tiptap/core/src/commands/focus.ts)
        // -- ohne diesen Tick wuerde der Test auch dann gruen bleiben, wenn
        // `.focus()` faelschlich im Chain stuende.
        await new Promise((resolve) => requestAnimationFrame(resolve));

        expect(focusSpy).not.toHaveBeenCalled();
        expect(dicomTagTable(editor).content[0].tag).toBe('(0010');
    });

    /**
     * `dicom_tag_table.content` verlangt `dicomTagRow+` (mind. eine Zeile)
     * -- die letzte verbleibende Zeile darf nie entfernbar sein.
     */
    it('disables removing the last remaining row', () => {
        const editor = editorWithRows([
            { tag: 'A', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const wrapper = mountFor(editor);

        const removeButton = wrapper.find('.rich-content-inspector-row-remove');
        expect(removeButton.attributes('disabled')).toBeDefined();
    });

    it('performs no writes while readonly', async () => {
        const editor = editorWithRows([
            { tag: 'A', keyword: '', vr: '', value: '' },
        ]);
        editor.commands.setNodeSelection(0);
        const block = resolveSelectedBlock(editor)!;
        const wrapper = mount(DicomTagTableInspector, {
            props: { editor, node: markRaw(block.node), readonly: true },
        });

        await wrapper.find('.rich-content-inspector-row-add').trigger('click');

        expect(dicomTagTable(editor).content).toHaveLength(1);
    });
});
