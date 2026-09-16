import type {
    RichContentBlockNode,
    RichContentCalloutKind,
    RichContentCodeBlockVariant,
    RichContentDicomTagRow,
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
 * Uebergehens (Betreiber-Vorgabe fuer CMS-7b/7c): `table` bleibt im
 * Editor-Extension-Satz weiterhin nicht unterstuetzt (generische Tabellen
 * sind kein Ziel von CMS-7c, siehe ADR 0114 -- nur `dicom_tag_table`
 * wurde editorfaehig) -- ein Dokument mit einem dieser Knoten darf beim
 * Laden nicht kommentarlos seinen Inhalt verlieren.
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
    self_check: 'selfCheck',
    callout: 'callout',
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
        case 'self_check':
        case 'callout': {
            const attrs: Record<string, unknown> | undefined =
                node.type === 'callout'
                    ? {
                          kind: node.attrs.kind,
                          ...(node.attrs.title !== undefined
                              ? { title: node.attrs.title }
                              : {}),
                      }
                    : node.type === 'self_check'
                      ? { summary: node.attrs.summary }
                      : undefined;

            return {
                type: tiptapType!,
                ...(attrs ? { attrs } : {}),
                content: node.content.map(toTipTapBlock),
            };
        }
        case 'dicom_tag_table':
            return {
                type: 'dicomTagTable',
                content: node.content.map((row) => toTipTapDicomTagRow(row)),
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

/**
 * Eine Tag-Zeile hat -- anders als jeder andere DCMLab-Knoten -- kein
 * eigenes `type` (reines Datenobjekt, siehe `RichContentDicomTagRow`), in
 * TipTap braucht sie trotzdem einen echten Knotentyp (`dicomTagRow`,
 * `customNodes.ts`), weil ProseMirror-Content immer aus Knoten besteht.
 */
function toTipTapDicomTagRow(row: RichContentDicomTagRow): TipTapNode {
    return { type: 'dicomTagRow', attrs: { ...row } };
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
        case 'glossary_term':
            return { type: 'glossaryTerm', attrs: { slug: node.attrs.slug } };
        default:
            // Alle heutigen RichContentInlineNode-Varianten sind oben
            // behandelt -- dieser Zweig ist nur eine Absicherung fuer eine
            // kuenftige, hier noch nicht nachgezogene Schema-Erweiterung
            // (siehe Klassendoc), TypeScript narrowt ihn deshalb auf `never`.
            throw new UnsupportedEditorNodeError(
                (node as { type: string }).type,
            );
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
        case 'selfCheck': {
            const summary = node.attrs?.summary;

            return {
                type: 'self_check',
                attrs: { summary: typeof summary === 'string' ? summary : '' },
                content: (node.content ?? []).map(fromTipTapBlock),
            };
        }
        case 'callout': {
            const kind = node.attrs?.kind;
            const title = node.attrs?.title;

            return {
                type: 'callout',
                attrs: {
                    kind:
                        kind === 'warning'
                            ? 'warning'
                            : ('info' satisfies RichContentCalloutKind),
                    ...(typeof title === 'string' ? { title } : {}),
                },
                content: (node.content ?? []).map(fromTipTapBlock),
            };
        }
        case 'dicomTagTable':
            return {
                type: 'dicom_tag_table',
                content: (node.content ?? []).map(fromTipTapDicomTagRow),
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

function fromTipTapDicomTagRow(node: TipTapNode): RichContentDicomTagRow {
    const attrs = node.attrs ?? {};
    const field = (key: keyof RichContentDicomTagRow): string =>
        typeof attrs[key] === 'string' ? (attrs[key] as string) : '';

    return {
        tag: field('tag'),
        keyword: field('keyword'),
        vr: field('vr'),
        value: field('value'),
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
        case 'glossaryTerm': {
            const slug = node.attrs?.slug;

            return {
                type: 'glossary_term',
                attrs: { slug: typeof slug === 'string' ? slug : '' },
            };
        }
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
