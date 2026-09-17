import { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { fromTipTap } from '@/lib/richContent/RichContentEditorAdapter';
import { richContentExtensions } from '@/lib/richContent/tiptapExtensions';
import RichContentToolbox from './RichContentToolbox.vue';

/**
 * `@tiptap/vue-3`'s `Editor` (not the plain `@tiptap/core` one used in
 * slashCommand.spec.ts/insertionAnchor.spec.ts) `markRaw()`s itself in its
 * constructor -- exactly what `useEditor()` relies on so a mounted
 * component can safely pass the instance through Vue props without Vue's
 * reactivity proxy wrapping it (which would otherwise break ProseMirror's
 * identity-sensitive transaction checks, "Applying a mismatched
 * transaction"). Since this file mounts a REAL component and passes the
 * editor as a prop, it must construct the editor the same way production
 * does.
 */
function editorWithEmptyParagraph(): Editor {
    return new Editor({
        extensions: richContentExtensions([]),
        content: { type: 'doc', content: [{ type: 'paragraph', content: [] }] },
    });
}

describe('RichContentToolbox', () => {
    it('renders exactly the 14 toolbox items grouped as Grundelemente/DCMLab/Struktur', () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, { props: { editor } });

        expect(
            wrapper
                .findAll('.rich-content-toolbox-group-title')
                .map((node) => node.text()),
        ).toEqual(['Grundelemente', 'DCMLab', 'Struktur']);
        expect(wrapper.findAll('.rich-content-toolbox-item')).toHaveLength(14);
    });

    it('does not list heading-3/4, ordered list, diagram or mermaid (slash-only capabilities)', () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, { props: { editor } });

        const labels = wrapper
            .findAll('.rich-content-toolbox-item')
            .map((node) => node.text());

        expect(labels).not.toContain('Überschrift 3');
        expect(labels).not.toContain('Überschrift 4');
        expect(labels).not.toContain('Nummerierte Liste');
        expect(labels).not.toContain('Diagramm');
        expect(labels).not.toContain('Mermaid');
    });

    it('never contains an entry for a non-Rich-Content type (Activities, Labs, Nodes, Quiz, Exam)', () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, { props: { editor } });

        const labels = wrapper
            .findAll('.rich-content-toolbox-item')
            .map((node) => node.text());

        expect(
            labels.some((label) => /lab|quiz|exam|node|activity/i.test(label)),
        ).toBe(false);
    });

    it('inserts the correct block into the editor when a toolbox item is clicked', async () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, { props: { editor } });

        const button = wrapper
            .findAll('.rich-content-toolbox-item')
            .find((node) => node.text().includes('Trennlinie'));
        await button!.trigger('click');

        const doc = fromTipTap(editor.getJSON());
        expect(
            doc.content.some((node) => node.type === 'horizontal_rule'),
        ).toBe(true);
    });

    it('inserts a code_block with the terminal variant for the Terminal item', async () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, { props: { editor } });

        const button = wrapper
            .findAll('.rich-content-toolbox-item')
            .find((node) => node.text() === 'Terminal');
        await button!.trigger('click');

        const doc = fromTipTap(editor.getJSON());
        expect(doc.content[0]).toMatchObject({
            type: 'code_block',
            attrs: { variant: 'terminal' },
        });
    });

    it('disables every button and performs no insertion when disabled', async () => {
        const editor = editorWithEmptyParagraph();
        const wrapper = mount(RichContentToolbox, {
            props: { editor, disabled: true },
        });

        const buttons = wrapper.findAll('.rich-content-toolbox-item');
        buttons.forEach((button) => {
            expect(button.attributes('disabled')).toBeDefined();
        });

        await buttons[0].trigger('click');
        const doc = fromTipTap(editor.getJSON());
        expect(doc.content).toEqual([{ type: 'paragraph', content: [] }]);
    });
});
