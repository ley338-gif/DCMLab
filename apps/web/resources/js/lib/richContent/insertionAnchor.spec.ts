import { Editor } from '@tiptap/core';
import { describe, expect, it } from 'vitest';
import {
    insertBlockAtAnchor,
    resolveInsertionPosition,
} from './insertionAnchor';
import { richContentExtensions } from './tiptapExtensions';
import type { JSONContent } from '@tiptap/core';

/**
 * Alle Faelle gegen einen echten TipTap-Editor (derselbe Extension-Satz wie
 * im Produkt) statt gegen simulierte ProseMirror-Positionen -- mirrors das
 * bereits bewaehrte Muster aus slashCommand.spec.ts. Positionsmathematik ist
 * notorisch leicht falsch anzunehmen; nur ein echter Editor beweist sie.
 */
function editorWithContent(content: JSONContent): Editor {
    return new Editor({
        extensions: richContentExtensions([]),
        content,
    });
}

const CALLOUT: JSONContent = {
    type: 'callout',
    attrs: { kind: 'info', title: null },
    content: [{ type: 'paragraph' }],
};

describe('resolveInsertionPosition', () => {
    it('inserts after the enclosing top-level paragraph when the cursor sits in non-empty text', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Hallo' }],
                },
            ],
        });
        // Cursor ans Ende von "Hallo" (Position 6: 1 vor dem Text + 5 Zeichen).
        editor.commands.setTextSelection(6);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'selection',
        });

        expect(resolved.replaceRange).toBeUndefined();
        expect(resolved.pos).toBe(editor.state.doc.content.size);
    });

    it('replaces the current block instead of inserting after it when the block is an empty paragraph', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [{ type: 'paragraph', content: [] }],
        });
        editor.commands.setTextSelection(1);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'selection',
        });

        expect(resolved.replaceRange).toEqual({ from: 0, to: 2 });
        expect(resolved.pos).toBe(0);
    });

    it('inserts after a whole NodeSelection instead of replacing it', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                { type: 'horizontalRule' },
                { type: 'paragraph', content: [] },
            ],
        });
        editor.commands.setNodeSelection(0);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'selection',
        });

        expect(resolved.replaceRange).toBeUndefined();
        expect(resolved.pos).toBe(1);
    });

    it('inserts after the immediately enclosing paragraph when the cursor is nested inside a list item, not after the whole list', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                {
                    type: 'bulletList',
                    content: [
                        {
                            type: 'listItem',
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [{ type: 'text', text: 'Eins' }],
                                },
                            ],
                        },
                        {
                            type: 'listItem',
                            content: [{ type: 'paragraph', content: [] }],
                        },
                    ],
                },
            ],
        });
        // Cursor ans Ende von "Eins" im ERSTEN listItem.
        editor.commands.setTextSelection(6);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'selection',
        });
        const docSize = editor.state.doc.content.size;

        // Die Position muss VOR dem Ende des Dokuments liegen (also
        // innerhalb der Liste, nicht dahinter) -- ein Einfuegen "nach dem
        // umschliessenden Block" darf hier nicht die ganze Liste verlassen.
        expect(resolved.pos).toBeLessThan(docSize);
        expect(resolved.replaceRange).toBeUndefined();
    });

    it('inserts after the immediately enclosing paragraph when the cursor is nested inside a table cell, not after the whole table', () => {
        const editor = editorWithContent({
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
                                                { type: 'text', text: 'Zelle' },
                                            ],
                                        },
                                    ],
                                },
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
                { type: 'paragraph', content: [] },
            ],
        });
        // Cursor ans Ende von "Zelle" in der ersten Tabellenzelle.
        editor.commands.setTextSelection(7);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'selection',
        });
        const table = editor.state.doc.firstChild!;

        // Die Position muss innerhalb der Tabelle liegen (kleiner als deren
        // Endposition), nicht dahinter im Dokument-Top-Level.
        expect(resolved.pos).toBeLessThan(table.nodeSize);
        expect(resolved.replaceRange).toBeUndefined();
    });

    it('resolves an explicit afterPos anchor directly, independent of the current selection', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Hallo' }],
                },
            ],
        });
        editor.commands.setTextSelection(1);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'afterPos',
            pos: 3,
        });

        expect(resolved).toEqual({ pos: 3 });
    });

    it('resolves endOfDocument to the document end regardless of the current selection', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Hallo' }],
                },
            ],
        });
        editor.commands.setTextSelection(1);

        const resolved = resolveInsertionPosition(editor, {
            kind: 'endOfDocument',
        });

        expect(resolved).toEqual({ pos: editor.state.doc.content.size });
    });
});

describe('insertBlockAtAnchor', () => {
    it('inserts the new block and leaves an existing text selection elsewhere untouched', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Hallo Welt' }],
                },
            ],
        });
        editor.commands.setTextSelection(6);

        insertBlockAtAnchor(editor, CALLOUT, { kind: 'selection' });

        const json = editor.getJSON();
        expect(json.content?.[0]).toMatchObject({
            type: 'paragraph',
            content: [{ type: 'text', text: 'Hallo Welt' }],
        });
        expect(json.content?.[1]).toMatchObject({
            type: 'callout',
            attrs: { kind: 'info' },
        });
    });

    it('replaces the empty paragraph itself instead of inserting after it', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [{ type: 'paragraph', content: [] }],
        });
        editor.commands.setTextSelection(1);

        insertBlockAtAnchor(editor, CALLOUT, { kind: 'selection' });

        // Der urspruengliche leere Absatz darf nicht VOR dem neuen Block
        // stehen bleiben -- er wurde ersetzt, nicht ergaenzt. (TipTaps
        // eigene TrailingNode-Erweiterung haengt danach ohnehin immer einen
        // frischen leeren Absatz an das Dokumentende an, unabhaengig davon
        // was eingefuegt wurde -- das ist bestehendes, unveraendertes
        // Verhalten, siehe die gleiche Erwartung in slashCommand.spec.ts.)
        const json = editor.getJSON();
        expect(json.content?.[0].type).toBe('callout');
    });

    it('selects the newly inserted block', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [{ type: 'paragraph', content: [] }],
        });
        editor.commands.setTextSelection(1);

        insertBlockAtAnchor(editor, CALLOUT, { kind: 'selection' });

        expect(editor.state.selection.constructor.name).toBe('NodeSelection');
        expect(
            (
                editor.state.selection as unknown as {
                    node: { type: { name: string } };
                }
            ).node.type.name,
        ).toBe('callout');
    });

    it('inserts a non-selectable-safe fallback when the target position has no selectable node (defensive)', () => {
        const editor = editorWithContent({
            type: 'doc',
            content: [{ type: 'paragraph', content: [] }],
        });

        expect(() =>
            insertBlockAtAnchor(
                editor,
                { type: 'horizontalRule' },
                { kind: 'endOfDocument' },
            ),
        ).not.toThrow();
    });
});
