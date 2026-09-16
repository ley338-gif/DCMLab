import { describe, expect, it } from 'vitest';
import {
    fromTipTap,
    toTipTap,
    UnsupportedEditorNodeError,
} from './RichContentEditorAdapter';
import type { RichContentDocument } from '@/types/richContent';

function doc(content: RichContentDocument['content']): RichContentDocument {
    return { type: 'doc', version: 1, content };
}

/**
 * Der wichtigste Test dieser Datei ist nicht "sieht die Konvertierung
 * richtig aus", sondern: DCMLab -> TipTap -> DCMLab ergibt wieder exakt
 * dasselbe Dokument (Betreiber-Vorgabe fuer CMS-7b).
 */
function assertRoundtrips(document: RichContentDocument) {
    expect(fromTipTap(toTipTap(document))).toEqual(document);
}

describe('RichContentEditorAdapter roundtrip', () => {
    it('roundtrips a paragraph', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Hallo.' }],
                },
            ]),
        );
    });

    it.each([2, 3, 4] as const)('roundtrips a heading level %d', (level) => {
        assertRoundtrips(
            doc([
                {
                    type: 'heading',
                    attrs: { level },
                    content: [{ type: 'text', text: 'Titel' }],
                },
            ]),
        );
    });

    it('roundtrips bold, italic and inline code marks', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'fett',
                            marks: [{ type: 'bold' }],
                        },
                        {
                            type: 'text',
                            text: 'kursiv',
                            marks: [{ type: 'italic' }],
                        },
                        {
                            type: 'text',
                            text: 'code',
                            marks: [{ type: 'code' }],
                        },
                        {
                            type: 'text',
                            text: 'fett+kursiv',
                            marks: [{ type: 'bold' }, { type: 'italic' }],
                        },
                    ],
                },
            ]),
        );
    });

    it('roundtrips a link mark', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [
                        {
                            type: 'text',
                            text: 'DCMLab',
                            marks: [
                                {
                                    type: 'link',
                                    attrs: { href: 'https://example.test' },
                                },
                            ],
                        },
                    ],
                },
            ]),
        );
    });

    it('roundtrips a bullet list', () => {
        assertRoundtrips(
            doc([
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
            ]),
        );
    });

    it('roundtrips an ordered list', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'ordered_list',
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
            ]),
        );
    });

    it('roundtrips a blockquote', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'blockquote',
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Zitat.' }],
                        },
                    ],
                },
            ]),
        );
    });

    it('roundtrips a hard break', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [
                        { type: 'text', text: 'Zeile eins' },
                        { type: 'hard_break' },
                        { type: 'text', text: 'Zeile zwei' },
                    ],
                },
            ]),
        );
    });

    it.each(['code', 'console', 'terminal', 'diagram', 'mermaid'] as const)(
        'roundtrips a code_block with variant %s and preserves the language',
        (variant) => {
            assertRoundtrips(
                doc([
                    {
                        type: 'code_block',
                        attrs: { variant, language: 'python' },
                        text: 'print(1)',
                    },
                ]),
            );
        },
    );

    it('roundtrips a code_block without a language', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'code_block',
                    attrs: { variant: 'terminal' },
                    text: '$ echoscu foo',
                },
            ]),
        );
    });

    it('does not silently turn a non-terminal variant into a plain code block', () => {
        const dcmlabDoc = doc([
            {
                type: 'code_block',
                attrs: { variant: 'console' },
                text: '$ echoscu foo',
            },
        ]);

        const tiptapDoc = toTipTap(dcmlabDoc);

        expect(tiptapDoc.content?.[0].attrs?.variant).toBe('console');
        expect(fromTipTap(tiptapDoc).content[0]).toEqual(dcmlabDoc.content[0]);
    });

    it('roundtrips a dicom_dump code_block', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'code_block',
                    attrs: { variant: 'dicom_dump' },
                    text: '(0010,0010) PN [DOE^JOHN]',
                },
            ]),
        );
    });

    it('roundtrips a self_check', () => {
        assertRoundtrips(
            doc([
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
            ]),
        );
    });

    it.each(['info', 'warning'] as const)(
        'roundtrips a %s callout with a title',
        (kind) => {
            assertRoundtrips(
                doc([
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
                ]),
            );
        },
    );

    it('roundtrips a callout without a title', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'callout',
                    attrs: { kind: 'info' },
                    content: [
                        {
                            type: 'paragraph',
                            content: [{ type: 'text', text: 'Hinweis.' }],
                        },
                    ],
                },
            ]),
        );
    });

    it('roundtrips a dicom_tag_table', () => {
        assertRoundtrips(
            doc([
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
            ]),
        );
    });

    it('roundtrips a horizontal_rule', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Davor.' }],
                },
                { type: 'horizontal_rule' },
                {
                    type: 'paragraph',
                    content: [{ type: 'text', text: 'Danach.' }],
                },
            ]),
        );
    });

    it('roundtrips a glossary_term', () => {
        assertRoundtrips(
            doc([
                {
                    type: 'paragraph',
                    content: [
                        { type: 'glossary_term', attrs: { slug: 'dicom' } },
                    ],
                },
            ]),
        );
    });
});

describe('RichContentEditorAdapter unsupported nodes', () => {
    it('throws for a table block instead of dropping it', () => {
        const dcmlabDoc = doc([
            {
                type: 'table',
                content: [
                    {
                        type: 'table_row',
                        content: [
                            {
                                type: 'table_cell',
                                attrs: { header: true },
                                content: [
                                    {
                                        type: 'paragraph',
                                        content: [
                                            { type: 'text', text: 'Tag' },
                                        ],
                                    },
                                ],
                            },
                        ],
                    },
                ],
            },
        ]);

        expect(() => toTipTap(dcmlabDoc)).toThrow(UnsupportedEditorNodeError);
    });

    it('throws for an unrecognized TipTap node when converting back', () => {
        expect(() =>
            fromTipTap({ type: 'doc', content: [{ type: 'someFutureNode' }] }),
        ).toThrow(UnsupportedEditorNodeError);
    });
});
