import { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import { markRaw } from 'vue';
import { describe, expect, it } from 'vitest';
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
