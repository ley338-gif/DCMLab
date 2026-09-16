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

    it('registers selfCheck, callout, dicomTagTable and glossaryTerm (CMS-7c), but not generic tables', () => {
        const schema = getSchema(richContentExtensions());

        expect(schema.nodes.selfCheck).toBeDefined();
        expect(schema.nodes.callout).toBeDefined();
        expect(schema.nodes.dicomTagTable).toBeDefined();
        expect(schema.nodes.dicomTagRow).toBeDefined();
        expect(schema.nodes.glossaryTerm).toBeDefined();
        expect(schema.nodes.table).toBeUndefined();
    });

    it('does not register strike or underline (not part of the DCMLab schema)', () => {
        const schema = getSchema(richContentExtensions());

        expect(schema.marks.strike).toBeUndefined();
        expect(schema.marks.underline).toBeUndefined();
    });

    /**
     * ADR 0116 (CMS-7d.1-Korrektur): `horizontal_rule` ist seit
     * `rich-content:audit` gegen echten Bestand ein echter Blocktyp --
     * TipTaps eingebaute HorizontalRule-Extension aus `StarterKit` reicht
     * dafuer aus, kein eigener Custom-Node.
     */
    it('registers horizontalRule and preserves it through the real schema', () => {
        const schema = getSchema(richContentExtensions());
        expect(schema.nodes.horizontalRule).toBeDefined();

        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Davor.' }],
                },
                { type: 'horizontal_rule' },
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Danach.' }],
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it('preserves a self_check through the real ProseMirror schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'self_check',
                    attrs: { summary: 'Frage?' },
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Antwort.' }],
                        },
                    ],
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it.each(['info', 'warning'] as const)(
        'preserves a %s callout with a title through the real schema',
        (kind) => {
            const doc: RichContentDocument = {
                type: 'doc',
                version: 1,
                content: [
                    {
                        type: 'callout',
                        attrs: { kind, title: 'Achtung' },
                        content: [
                            {
                                type: 'paragraph',
                                content: [{ type: 'text', text: 'Vorsicht.' }],
                            },
                        ],
                    },
                ],
            };

            expect(roundtripThroughRealSchema(doc)).toEqual(doc);
        },
    );

    it('preserves a dicom_tag_table with multiple rows through the real schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'dicom_tag_table',
                    content: [
                        {
                            tag: '(0010,0010)',
                            keyword: 'PatientName',
                            vr: 'PN',
                            value: 'DOE^JOHN',
                        },
                        {
                            tag: '(0008,0060)',
                            keyword: 'Modality',
                            vr: 'CS',
                            value: 'CT',
                        },
                    ],
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it('preserves a glossary_term through the real schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'paragraph',
                    content: [
                        { type: 'glossary_term', attrs: { slug: 'dicom' } },
                    ],
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });

    it('preserves the dicom_dump code_block variant through the real schema', () => {
        const doc: RichContentDocument = {
            type: 'doc',
            version: 1,
            content: [
                {
                    type: 'code_block',
                    attrs: { variant: 'dicom_dump' },
                    text: '(0010,0010) PN [DOE^JOHN]',
                },
            ],
        };

        expect(roundtripThroughRealSchema(doc)).toEqual(doc);
    });
});
