/**
 * DCMLab Rich-Content-Dokument v1 (ADR 0111/0112, CMS-7a) -- dieselbe Form,
 * die `App\Content\RichContent\RichContentValidator` serverseitig prueft.
 * TipTap (CMS-7b) kennt dieses Format nicht direkt, siehe
 * `RichContentEditorAdapter` -- diese Typen sind bewusst unabhaengig von
 * jedem TipTap/ProseMirror-Typ gehalten.
 */

export type RichContentMark =
    | { type: 'bold' }
    | { type: 'italic' }
    | { type: 'code' }
    | { type: 'link'; attrs: { href: string } };

export type RichContentTextNode = {
    type: 'text';
    text: string;
    marks?: RichContentMark[];
};

export type RichContentGlossaryTermNode = {
    type: 'glossary_term';
    attrs: { slug: string };
};

export type RichContentHardBreakNode = {
    type: 'hard_break';
};

export type RichContentInlineNode =
    | RichContentTextNode
    | RichContentGlossaryTermNode
    | RichContentHardBreakNode;

export type RichContentParagraphNode = {
    type: 'paragraph';
    content: RichContentInlineNode[];
};

export type RichContentHeadingNode = {
    type: 'heading';
    attrs: { level: 1 | 2 | 3 | 4 | 5 | 6 };
    content: RichContentInlineNode[];
};

export type RichContentListItemNode = {
    type: 'list_item';
    content: RichContentBlockNode[];
};

export type RichContentBulletListNode = {
    type: 'bullet_list';
    content: RichContentListItemNode[];
};

export type RichContentOrderedListNode = {
    type: 'ordered_list';
    content: RichContentListItemNode[];
};

export type RichContentBlockquoteNode = {
    type: 'blockquote';
    content: RichContentBlockNode[];
};

export type RichContentCodeBlockVariant =
    | 'code'
    | 'console'
    | 'terminal'
    | 'diagram'
    | 'mermaid';

export type RichContentCodeBlockNode = {
    type: 'code_block';
    attrs: { variant: RichContentCodeBlockVariant; language?: string };
    text: string;
};

export type RichContentTableCellNode = {
    type: 'table_cell';
    attrs: { header: boolean };
    content: RichContentBlockNode[];
};

export type RichContentTableRowNode = {
    type: 'table_row';
    content: RichContentTableCellNode[];
};

export type RichContentTableNode = {
    type: 'table';
    content: RichContentTableRowNode[];
};

export type RichContentSelfCheckNode = {
    type: 'self_check';
    attrs: { summary: string };
    content: RichContentBlockNode[];
};

export type RichContentBlockNode =
    | RichContentParagraphNode
    | RichContentHeadingNode
    | RichContentBulletListNode
    | RichContentOrderedListNode
    | RichContentBlockquoteNode
    | RichContentCodeBlockNode
    | RichContentTableNode
    | RichContentSelfCheckNode;

export type RichContentDocument = {
    type: 'doc';
    version: 1;
    content: RichContentBlockNode[];
};

/**
 * Alles, was in einem DCMLab-Dokument als `type` vorkommen kann -- Bloecke
 * und Inline-Knoten zusammen, ohne "doc"/"list_item"/"table_row"/
 * "table_cell" (die tauchen nie als eigenstaendiges Top-Level-Element in
 * `validateBlock()`s Kontext auf, siehe RichContentValidator).
 */
export const RICH_CONTENT_BLOCK_TYPES = [
    'paragraph',
    'heading',
    'bullet_list',
    'ordered_list',
    'blockquote',
    'code_block',
    'table',
    'self_check',
] as const;
