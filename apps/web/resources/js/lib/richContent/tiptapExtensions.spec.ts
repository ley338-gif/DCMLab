import { getSchema } from '@tiptap/core';
import { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { describe, expect, it } from 'vitest';
import { fromTipTap, toTipTap } from './RichContentEditorAdapter';
import { richContentExtensions } from './tiptapExtensions';
import type { RichContentDocument } from '@/types/richContent';

/**
 * Unterscheidet sich von `RichContentEditorAdapter.spec.ts`: dort werden
 * nur die eigenen TypeScript-Funktionen gegeneinander getestet, hier
 * laeuft das TipTap-JSON tatsaechlich durch das echte ProseMirror-Schema
 * (`Node.fromJSON()`/`toJSON()`, derselbe Weg, den `editor.getJSON()`
 * intern nimmt) -- genau der Fall, in dem ein nicht im Schema deklariertes
 * Attribut (z. B. `code_block.attrs.variant`, wenn `RichContentCodeBlock`
 * es nicht ergaenzt haette) stillschweigend verschwinden wuerde, ohne dass
 * die reinen Adapter-Funktionen das je bemerken koennten.
 */
function roundtripThroughRealSchema(
    doc: RichContentDocument,
): RichContentDocument {
    const schema = getSchema(richContentExtensions());
    const tiptapJson = toTipTap(doc);
    const pmNode = ProseMirrorNode.fromJSON(schema, tiptapJson);

    return fromTipTap(pmNode.toJSON());
}

describe('richContentExtensions schema roundtrip', () => {
    it('preserves code_block variant and language through the real ProseMirror schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'code_block',
                    attrs: { variant: 'console', language: 'python' },
                    text: 'print(1)',
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it.each(['code', 'console', 'terminal', 'diagram', 'mermaid'] as const)(
        'preserves the %s variant without a language through the real schema',
        (variant) => {
            const doc: RichContentDocument = {
                type: 'doc',
                version: 1,
                content: [
                    {
                        type: 'code_block',
                        attrs: { variant },
                        text: '$ echoscu foo',
                    },
                ],
            };

            expect(roundtripThroughRealSchema(doc)).toEqual(doc);
        },
    );

    it('preserves every configured block/mark type through the real schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'heading',
                    attrs: { level: 3 },
                    content: [{ type: 'text', text: 'Titel' }],
                },
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'fett',
                            marks: [{ type: 'bold' }],
                        },
                        { type: 'text', text: ' ' },
                        {
                            type: 'text',
                            text: 'kursiv',
                            marks: [{ type: 'italic' }],
                        },
                        { type: 'hard_break' },
                        {
                            type: 'text',
                            text: 'Link',
                            marks: [
                                {
                                    type: 'link',
                                    attrs: { href: 'https://example.test' },
                                },
                            ],
                        },
                    ],
                },
                {
                    type: 'bullet_list',
                    content: [
                        {
                            type: 'list_item',
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [{ type: 'text', text: 'Eins' }],
                                },
                            ],
                        },
                    ],
                },
                {
                    type: 'ordered_list',
                    content: [
                        {
                            type: 'list_item',
                            content: [
                                {
                                    type: 'paragraph',
                                    content: [{ type: 'text', text: 'Zwei' }],
                                },
                            ],
                        },
                    ],
                },
                {
                    type: 'blockquote',
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Zitat.' }],
                        },
                    ],
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it('does not register self_check, table or glossary_term in the editor schema', () => {
        const schema = getSchema(richContentExtensions());

        expect(schema.nodes.self_check).toBeUndefined();
        expect(schema.nodes.table).toBeUndefined();
        expect(schema.nodes.glossary_term).toBeUndefined();
    });

    it('does not register strike, underline or horizontalRule (not part of the DCMLab schema)', () => {
        const schema = getSchema(richContentExtensions());

        expect(schema.marks.strike).toBeUndefined();
        expect(schema.marks.underline).toBeUndefined();
        expect(schema.nodes.horizontalRule).toBeUndefined();
    });
});
