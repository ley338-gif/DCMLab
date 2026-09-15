import { CodeBlock } from '@tiptap/extension-code-block';
import StarterKit from '@tiptap/starter-kit';
import type { AnyExtension } from '@tiptap/core';
import type { RichContentCodeBlockVariant } from '@/types/richContent';

/**
 * TipTaps eingebaute `codeBlock`-Extension kennt nur `language` -- DCMLabs
 * `code_block.attrs.variant` (ADR 0111) haette ProseMirror sonst beim
 * naechsten `Node.fromJSON()`/`getJSON()` stillschweigend verworfen, weil
 * ein nicht im Schema deklariertes Attribut nie ankommt. `variant` deshalb
 * hier als echtes, mit einem Default versehenes Attribut ergaenzt (CMS-7b,
 * siehe ADR-Betreiber-Review: "code_block.attrs bleiben erhalten").
 */
export const RichContentCodeBlock = CodeBlock.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            variant: {
                default: 'terminal' satisfies RichContentCodeBlockVariant,
            },
        };
    },
});

/**
 * Der Extension-Satz fuer CMS-7b (Betreiber-Scope): Paragraph, Heading
 * 2-4, Bold, Italic, Inline Code, Link, Bullet/Ordered List, Blockquote,
 * Code Block, Undo/Redo -- bewusst nicht `self_check`/Tabellen/
 * `glossary_term` (folgen mit CMS-7c) und nicht Strike/Underline/
 * HorizontalRule (nicht Teil des DCMLab-Schemas, ADR 0111). Jeder Knoten,
 * den `RichContentEditorAdapter` nicht kennt, wirft dort explizit statt
 * hier still zu verschwinden -- dieser Extension-Satz ist deshalb absichtlich
 * eng, nicht defensiv breit.
 */
export function richContentExtensions(): AnyExtension[] {
    return [
        StarterKit.configure({
            codeBlock: false,
            strike: false,
            underline: false,
            horizontalRule: false,
            heading: { levels: [2, 3, 4] },
            link: { openOnClick: false, autolink: false },
        }),
        RichContentCodeBlock,
    ];
}
