import type { JSONContent } from '@tiptap/core';
import { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import { markRaw } from 'vue';
import { describe, expect, it } from 'vitest';
import { resolveSelectedBlock } from '@/lib/richContent/insertionAnchor';
import { richContentExtensions } from '@/lib/richContent/tiptapExtensions';
import RichContentInspector from './RichContentInspector.vue';
import type { RichContentBlockDefinition } from '@/lib/richContent/blockDefinitions';

/**
 * `@tiptap/vue-3`'s `Editor` markRaw()s itself -- required whenever an
 * editor instance is passed through a mounted component's props, see
 * RichContentToolbox.spec.ts for the full explanation.
 */
function editorWith(content: JSONContent): Editor {
    return new Editor({ extensions: richContentExtensions([]), content });
}

/**
 * `markRaw(block.node)`: mirrors RichContentWorkbench.vue's own
 * `recomputeSelectedBlock()` -- a ProseMirror node passed unmarked through
 * Vue props gets reactively proxied, which breaks its identity-sensitive
 * `isLeaf`/`nodeSize` getters (see DicomTagTableInspector.spec.ts for the
 * regression this guards against).
 */
function selectedBlockFor(
    editor: Editor,
    definition?: RichContentBlockDefinition,
) {
    const block = resolveSelectedBlock(editor)!;
    return { pos: block.pos, node: markRaw(block.node), definition };
}

describe('RichContentInspector', () => {
    it('shows a placeholder when nothing is selected', () => {
        const wrapper = mount(RichContentInspector, {
            props: { editor: undefined, selectedBlock: null },
        });

        expect(wrapper.text()).toContain('Kein Block ausgewählt');
    });

    it('shows "no settings" for a block type without a sub-inspector (paragraph)', () => {
        const editor = editorWith({
            type: 'doc',
            content: [{ type: 'paragraph', content: [] }],
        });
        editor.commands.setTextSelection(1);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        expect(wrapper.text()).toContain('Keine Einstellungen');
    });

    it('renders the Heading inspector and updates the level', async () => {
        const editor = editorWith({
            type: 'doc',
            content: [
                {
                    type: 'heading',
                    attrs: { level: 2 },
                    content: [{ type: 'text', text: 'Titel' }],
                },
            ],
        });
        editor.commands.setTextSelection(2);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        const select = wrapper.findComponent({ name: 'SelectRoot' });
        expect(select.exists()).toBe(true);

        // Direkter Aufruf des Emits, um die reka-ui-Select-Interna nicht
        // nachbauen zu muessen -- prueft dieselbe Kette wie ein echter Klick
        // (SelectRoot emits update:modelValue -> HeadingInspector.setLevel).
        await select.vm.$emit('update:modelValue', '3');
        await wrapper.vm.$nextTick();

        expect(editor.getJSON().content?.[0]).toMatchObject({
            type: 'heading',
            attrs: { level: 3 },
        });
    });

    it('renders the Callout inspector and updates kind and title', async () => {
        const editor = editorWith({
            type: 'doc',
            content: [
                {
                    type: 'callout',
                    attrs: { kind: 'info', title: null },
                    content: [{ type: 'paragraph', content: [] }],
                },
            ],
        });
        editor.commands.setTextSelection(2);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        const titleInput = wrapper.find('input[placeholder="(kein Titel)"]');
        await titleInput.setValue('Achtung');

        expect(editor.getJSON().content?.[0]).toMatchObject({
            type: 'callout',
            attrs: { kind: 'info', title: 'Achtung' },
        });
    });

    it('renders the SelfCheck inspector and updates the summary', async () => {
        const editor = editorWith({
            type: 'doc',
            content: [
                {
                    type: 'selfCheck',
                    attrs: { summary: 'Antwort anzeigen' },
                    content: [{ type: 'paragraph', content: [] }],
                },
            ],
        });
        editor.commands.setTextSelection(2);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        const input = wrapper.find('input');
        await input.setValue('Lösung zeigen');

        expect(editor.getJSON().content?.[0]).toMatchObject({
            type: 'selfCheck',
            attrs: { summary: 'Lösung zeigen' },
        });
    });

    it('renders the DicomTagTable inspector with one row per dicomTagRow', () => {
        const editor = editorWith({
            type: 'doc',
            content: [
                {
                    type: 'dicomTagTable',
                    content: [
                        {
                            type: 'dicomTagRow',
                            attrs: {
                                tag: '(0010,0010)',
                                keyword: 'PatientName',
                                vr: 'PN',
                                value: '',
                            },
                        },
                        {
                            type: 'dicomTagRow',
                            attrs: { tag: '', keyword: '', vr: '', value: '' },
                        },
                    ],
                },
            ],
        });
        editor.commands.setNodeSelection(0);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        expect(
            wrapper.findAll('.rich-content-inspector-dicom-row'),
        ).toHaveLength(2);
    });

    it('renders the Table inspector with operation buttons for a table', () => {
        const editor = editorWith({
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
                                        { type: 'paragraph', content: [] },
                                    ],
                                },
                            ],
                        },
                    ],
                },
            ],
        });
        editor.commands.setTextSelection(3);

        const wrapper = mount(RichContentInspector, {
            props: { editor, selectedBlock: selectedBlockFor(editor) },
        });

        expect(wrapper.text()).toContain('Zeile danach einfügen');
    });

    it('never writes when readonly is set', async () => {
        const editor = editorWith({
            type: 'doc',
            content: [
                {
                    type: 'selfCheck',
                    attrs: { summary: 'Antwort anzeigen' },
                    content: [{ type: 'paragraph', content: [] }],
                },
            ],
        });
        editor.commands.setTextSelection(2);

        const wrapper = mount(RichContentInspector, {
            props: {
                editor,
                selectedBlock: selectedBlockFor(editor),
                readonly: true,
            },
        });

        const input = wrapper.find('input');
        expect(input.attributes('disabled')).toBeDefined();
    });
});
