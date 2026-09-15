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
});

describe('RichContentEditorAdapter unsupported nodes', () => {
    it('throws for a self_check block instead of dropping it', () => {
        const dcmlabDoc = doc([
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
        ]);

        expect(() => toTipTap(dcmlabDoc)).toThrow(UnsupportedEditorNodeError);
        expect(() => toTipTap(dcmlabDoc)).toThrow(
            'Unsupported editor node: self_check',
        );
    });

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

    it('throws for a glossary_term inline node instead of dropping it', () => {
        const dcmlabDoc = doc([
            {
                type: 'paragraph',
                content: [{ type: 'glossary_term', attrs: { slug: 'dicom' } }],
            },
        ]);

        expect(() => toTipTap(dcmlabDoc)).toThrow(
            'Unsupported editor node: glossary_term',
        );
    });

    it('throws for an unrecognized TipTap node when converting back', () => {
        expect(() =>
            fromTipTap({ type: 'doc', content: [{ type: 'someFutureNode' }] }),
        ).toThrow(UnsupportedEditorNodeError);
    });
});
