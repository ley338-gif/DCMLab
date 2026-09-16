import { CodeBlock } from '@tiptap/extension-code-block';
import StarterKit from '@tiptap/starter-kit';
import type { AnyExtension } from '@tiptap/core';
import {
    Callout,
    DicomTagRow,
    DicomTagTable,
    GlossaryTerm,
    SelfCheck,
} from '@/lib/richContent/customNodes';
import {
    SlashCommand,
    type GlossaryTermOption,
} from '@/lib/richContent/slashCommand';
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
 * Der Extension-Satz -- CMS-7b lieferte Paragraph, Heading 2-4, Bold,
 * Italic, Inline Code, Link, Bullet/Ordered List, Blockquote, Code Block,
 * Undo/Redo; CMS-7c (ADR 0114) ergaenzt `callout`, `self_check`,
 * `dicom_tag_table` und `glossary_term` (jetzt durchsuchbar/einfuegbar
 * ueber das Slash-Menue) sowie die `dicom_dump`-Code-Block-Variante (kein
 * Schema-Zusatz, nur ein weiterer `attrs.variant`-Wert); CMS-7d.1 (ADR
 * 0116) ergaenzt `horizontal_rule` -- TipTaps eingebaute HorizontalRule-
 * Extension aus `StarterKit` reicht dafuer unveraendert aus, kein eigener
 * Custom-Node noetig. Bewusst weiterhin nicht: generische Tabellen
 * (`table`, kein Ziel von CMS-7c) und Strike/Underline (nicht Teil des
 * DCMLab-Schemas, ADR 0111). Jeder Knoten, den `RichContentEditorAdapter`
 * nicht kennt, wirft dort explizit statt hier still zu verschwinden --
 * dieser Extension-Satz ist deshalb absichtlich eng, nicht defensiv breit.
 *
 * @param  glossaryTerms  fuer das `/glossary`-Slash-Kommando (Suche +
 *                        Einfuegen eines `glossary_term`-Knotens,
 *                        ADR 0114) -- leer, wenn der Aufrufer (noch) keine
 *                        Glossarliste hat.
 */
export function richContentExtensions(
    glossaryTerms: GlossaryTermOption[] = [],
): AnyExtension[] {
    return [
        StarterKit.configure({
            codeBlock: false,
            strike: false,
            underline: false,
            heading: { levels: [2, 3, 4] },
            link: { openOnClick: false, autolink: false },
        }),
        RichContentCodeBlock,
        Callout,
        SelfCheck,
        DicomTagRow,
        DicomTagTable,
        GlossaryTerm,
        SlashCommand.configure({ glossaryTerms }),
    ];
}
