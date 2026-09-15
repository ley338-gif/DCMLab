import type {
    RichContentBlockNode,
    RichContentCodeBlockVariant,
    RichContentDocument,
    RichContentInlineNode,
    RichContentListItemNode,
    RichContentMark,
    RichContentTextNode,
} from '@/types/richContent';

/**
 * Ein TipTap/ProseMirror-JSON-Knoten -- absichtlich lose typisiert
 * (`Record<string, unknown>`-Attribute), weil TipTap selbst kein
 * geschlossenes TS-Modell fuer sein JSON exportiert (`JSONContent` aus
 * `@tiptap/core` ist ebenso offen).
 */
export type TipTapMark = { type: string; attrs?: Record<string, unknown> };

export type TipTapNode = {
    type: string;
    attrs?: Record<string, unknown>;
    content?: TipTapNode[];
    text?: string;
    marks?: TipTapMark[];
};

/**
 * Ein DCMLab- oder TipTap-Knotentyp, den `RichContentEditorAdapter` (noch)
 * nicht kennt -- absichtlich eine Exception statt eines stillen
 * Uebergehens (Betreiber-Vorgabe fuer CMS-7b): `self_check`, `table` und
 * `glossary_term` sind im Editor-Extension-Satz von CMS-7b bewusst noch
 * nicht unterstuetzt (folgen mit CMS-7c) -- ein Dokument mit einem dieser
 * Knoten darf beim Laden nicht kommentarlos seinen Inhalt verlieren.
 */
export class UnsupportedEditorNodeError extends Error {
    constructor(public readonly nodeType: string) {
        super(`Unsupported editor node: ${nodeType}`);
        this.name = 'UnsupportedEditorNodeError';
    }
}

const BLOCK_TYPE_TO_TIPTAP: Partial<
    Record<RichContentBlockNode['type'], string>
> = {
    paragraph: 'paragraph',
    heading: 'heading',
    bullet_list: 'bulletList',
    ordered_list: 'orderedList',
    blockquote: 'blockquote',
    code_block: 'codeBlock',
};

const MARK_TYPE_TO_TIPTAP: Record<'bold' | 'italic' | 'code', string> = {
    bold: 'bold',
    italic: 'italic',
    code: 'code',
};

/**
 * Uebersetzt ein DCMLab-v1-Dokument (ADR 0111/0112) in TipTap/ProseMirror-
 * JSON -- die einzige Richtung, in der `self_check`/`table`/
 * `glossary_term` ueberhaupt auftreten koennen (sie kommen aus der DB, nie
 * aus dem Editor selbst), deshalb der Ort, an dem
 * `UnsupportedEditorNodeError` tatsaechlich greift.
 */
export function toTipTap(doc: RichContentDocument): TipTapNode {
    return { type: 'doc', content: doc.content.map(toTipTapBlock) };
}

function toTipTapBlock(node: RichContentBlockNode): TipTapNode {
    const tiptapType = BLOCK_TYPE_TO_TIPTAP[node.type];

    switch (node.type) {
        case 'paragraph':
        case 'heading': {
            const attrs =
                node.type === 'heading'
                    ? { level: node.attrs.level }
                    : undefined;

            return {
                type: tiptapType!,
                ...(attrs ? { attrs } : {}),
                content: node.content.map(toTipTapInline),
            };
        }
        case 'bullet_list':
        case 'ordered_list':
            return {
                type: tiptapType!,
                content: node.content.map(toTipTapListItem),
            };
        case 'blockquote':
            return {
                type: 'blockquote',
                content: node.content.map(toTipTapBlock),
            };
        case 'code_block': {
            const attrs: Record<string, unknown> = {
                variant: node.attrs.variant,
            };

            if (node.attrs.language !== undefined) {
                attrs.language = node.attrs.language;
            }

            return {
                type: 'codeBlock',
                attrs,
                content:
                    node.text === '' ? [] : [{ type: 'text', text: node.text }],
            };
        }
        default:
            throw new UnsupportedEditorNodeError(node.type);
    }
}

function toTipTapListItem(item: RichContentListItemNode): TipTapNode {
    return { type: 'listItem', content: item.content.map(toTipTapBlock) };
}

function toTipTapInline(node: RichContentInlineNode): TipTapNode {
    switch (node.type) {
        case 'text': {
            const result: TipTapNode = { type: 'text', text: node.text };

            if (node.marks && node.marks.length > 0) {
                result.marks = node.marks.map(toTipTapMark);
            }

            return result;
        }
        case 'hard_break':
            return { type: 'hardBreak' };
        default:
            throw new UnsupportedEditorNodeError(node.type);
    }
}

function toTipTapMark(mark: RichContentMark): TipTapMark {
    if (mark.type === 'link') {
        return { type: 'link', attrs: { href: mark.attrs.href } };
    }

    return { type: MARK_TYPE_TO_TIPTAP[mark.type] };
}

/**
 * Uebersetzt TipTap/ProseMirror-JSON zurueck in ein DCMLab-v1-Dokument --
 * das Gegenstueck zu `toTipTap()`. TipTaps eigenes Schema kann strukturell
 * nur Knoten enthalten, die eine konfigurierte Extension kennt (siehe
 * `richContentExtensions()`); der `default`-Zweig unten ist trotzdem kein
 * stiller Fallback, sondern wirft, falls doch je ein unbekannter Typ
 * ankommt -- z. B. wenn jemand die Extension-Liste erweitert, ohne den
 * Adapter mitzuziehen.
 */
export function fromTipTap(doc: TipTapNode): RichContentDocument {
    return {
        type: 'doc',
        version: 1,
        content: (doc.content ?? []).map(fromTipTapBlock),
    };
}

function fromTipTapBlock(node: TipTapNode): RichContentBlockNode {
    switch (node.type) {
        case 'paragraph':
            return {
                type: 'paragraph',
                content: (node.content ?? []).map(fromTipTapInline),
            };
        case 'heading':
            return {
                type: 'heading',
                attrs: {
                    level: (node.attrs?.level as 1 | 2 | 3 | 4 | 5 | 6) ?? 2,
                },
                content: (node.content ?? []).map(fromTipTapInline),
            };
        case 'bulletList':
            return {
                type: 'bullet_list',
                content: (node.content ?? []).map(fromTipTapListItem),
            };
        case 'orderedList':
            return {
                type: 'ordered_list',
                content: (node.content ?? []).map(fromTipTapListItem),
            };
        case 'blockquote':
            return {
                type: 'blockquote',
                content: (node.content ?? []).map(fromTipTapBlock),
            };
        case 'codeBlock': {
            const text = (node.content ?? [])
                .map((child) => child.text ?? '')
                .join('');
            const variant =
                (node.attrs?.variant as
                    | RichContentCodeBlockVariant
                    | undefined) ?? 'terminal';
            const language = node.attrs?.language as string | null | undefined;

            return {
                type: 'code_block',
                attrs: language ? { variant, language } : { variant },
                text,
            };
        }
        default:
            throw new UnsupportedEditorNodeError(node.type);
    }
}

function fromTipTapListItem(node: TipTapNode): RichContentListItemNode {
    return {
        type: 'list_item',
        content: (node.content ?? []).map(fromTipTapBlock),
    };
}

function fromTipTapInline(node: TipTapNode): RichContentInlineNode {
    switch (node.type) {
        case 'text': {
            const result: RichContentTextNode = {
                type: 'text',
                text: node.text ?? '',
            };

            if (node.marks && node.marks.length > 0) {
                result.marks = node.marks.map(fromTipTapMark);
            }

            return result;
        }
        case 'hardBreak':
            return { type: 'hard_break' };
        default:
            throw new UnsupportedEditorNodeError(node.type);
    }
}

function fromTipTapMark(mark: TipTapMark): RichContentMark {
    if (mark.type === 'link') {
        const href = mark.attrs?.href;

        return {
            type: 'link',
            attrs: { href: typeof href === 'string' ? href : '' },
        };
    }

    if (
        mark.type === 'bold' ||
        mark.type === 'italic' ||
        mark.type === 'code'
    ) {
        return { type: mark.type };
    }

    throw new UnsupportedEditorNodeError(mark.type);
}
