import { NodeSelection, TextSelection } from '@tiptap/pm/state';
import type { Editor, JSONContent } from '@tiptap/core';

/**
 * Ein Einfuegepunkt fuer einen neuen Block -- bewusst NIE implizit "wo der
 * Cursor gerade zufaellig steht": ein Klick in der Toolbox/im Inspector/auf
 * ein block-eigenes "+" passiert ausserhalb des Editors, zum Zeitpunkt des
 * Klicks kann die zuletzt bekannte Selektion beliebig veraltet sein. Jeder
 * Aufrufer entscheidet deshalb explizit, WAS "hier einfuegen" bedeutet:
 *
 * - `selection`: Toolbox/Slash-Menue -- aus der aktuellen Editor-Selektion
 *   ableiten (siehe `resolveInsertionPosition()`).
 * - `afterPos`: ein block-eigenes "+" -- `pos` kommt aus der eigenen
 *   `getPos()` einer NodeView im Moment des Klicks, nie aus einem frueher
 *   zwischengespeicherten Wert (siehe RichContentBlockChrome.vue).
 * - `endOfDocument`: das "+" am Dokumentende.
 */
export type InsertionAnchor =
    | { kind: 'selection' }
    | { kind: 'afterPos'; pos: number }
    | { kind: 'endOfDocument' };

export type ResolvedInsertion = {
    pos: number;
    /** Gesetzt, wenn der aktuelle Block ein leerer Absatz ist, der ersetzt
     *  (nicht ergaenzt) werden soll -- siehe Regel 4 unten. */
    replaceRange?: { from: number; to: number };
};

/**
 * Ermittelt aus einem `InsertionAnchor` eine konkrete ProseMirror-Position
 * (und ggf. einen zu ersetzenden Bereich) -- reine Positions-Mathematik,
 * unabhaengig von einem konkreten Blocktyp, deshalb eigenstaendig
 * unit-testbar statt ueber jede `RichContentBlockDefinition` verstreut.
 *
 * Fuer `{kind:'selection'}` gilt (Betreiber-Vorgabe):
 * 1. Cursor in einem nicht-leeren Absatz -> NACH dem unmittelbar
 *    umschliessenden Block einfuegen (nie mitten hineinsplitten). "Der
 *    umschliessende Block" ist bewusst per `$from.depth` bestimmt, nicht
 *    fest Tiefe 1 -- ein Cursor in einer Tabellenzelle/Listenzeile soll
 *    innerhalb dieser Zelle/Zeile einfuegen, nicht die ganze Tabelle/Liste
 *    verlassen (siehe Tests fuer verschachtelte Faelle).
 * 2. Ein ganzer Block ist per NodeSelection markiert -> danach einfuegen,
 *    nie ersetzen.
 * 3. Keine sinnvolle/kollabierte Selektion -> Dokumentende.
 * 4. Der aktuelle Block ist ein LEERER Absatz -> ersetzen statt danach
 *    einzufuegen (keine verwaiste Leerzeile).
 * 5. Eine bestehende Text-Selektion wird nie geloescht -- nur ein leerer
 *    Absatz wird je ersetzt, nie eine echte Auswahl.
 */
export function resolveInsertionPosition(
    editor: Editor,
    anchor: InsertionAnchor,
): ResolvedInsertion {
    if (anchor.kind === 'endOfDocument') {
        return { pos: editor.state.doc.content.size };
    }

    if (anchor.kind === 'afterPos') {
        return { pos: anchor.pos };
    }

    const { selection } = editor.state;

    if (selection instanceof NodeSelection) {
        return { pos: selection.to };
    }

    const { $from } = selection;
    const depth = $from.depth;

    if (depth === 0) {
        // Keine umschliessende Block-Ebene ueberhaupt (sollte bei einem
        // schema-gueltigen Dokument, `doc: 'block+'`, nicht vorkommen) --
        // deterministischer Fallback statt eines ungueltigen Zugriffs.
        return { pos: editor.state.doc.content.size };
    }

    const blockStart = $from.before(depth);
    const blockEnd = $from.after(depth);
    const isEmptyParagraph =
        $from.parent.type.name === 'paragraph' &&
        $from.parent.content.size === 0;

    if (isEmptyParagraph) {
        return {
            pos: blockStart,
            replaceRange: { from: blockStart, to: blockEnd },
        };
    }

    return { pos: blockEnd };
}

/**
 * Fuehrt die eigentliche Einfuegung aus und waehlt den neu eingefuegten
 * Block anschliessend aus (fuer Inspector/Chrome).
 *
 * Ersetzt einen leeren Absatz ueber `insertContentAt({from, to}, ...)` --
 * ein EINZELNER Replace-Schritt -- statt vorher separat `deleteRange()`
 * aufzurufen: ProseMirror haelt ein Dokument nach JEDEM einzelnen Schritt
 * schema-gueltig (`doc: 'block+'`), ein isoliertes Loeschen des kompletten
 * (einzigen) Blocks wuerde deshalb sofort einen leeren Ersatz-Absatz
 * nachziehen, BEVOR der neue Block eingefuegt wird -- Ergebnis waere der
 * neue Block PLUS ein uebrig gebliebener leerer Absatz statt nur der neue
 * Block.
 *
 * `NodeSelection.create` wirft fuer einen nicht selektierbaren Knoten
 * (z. B. `dicomTagRow`, hier nie direkt als Top-Level-Block eingefuegt,
 * aber defensiv trotzdem abgefangen); in dem Fall faellt die Selektion auf
 * eine Textselektion an derselben Position zurueck, statt die ganze
 * Transaktion abzubrechen.
 */
export function insertBlockAtAnchor(
    editor: Editor,
    content: JSONContent,
    anchor: InsertionAnchor,
): void {
    const resolved = resolveInsertionPosition(editor, anchor);
    const target = resolved.replaceRange ?? resolved.pos;

    editor
        .chain()
        .focus()
        .insertContentAt(target, content)
        .command(({ tr, dispatch }) => {
            if (dispatch) {
                try {
                    tr.setSelection(NodeSelection.create(tr.doc, resolved.pos));
                } catch {
                    tr.setSelection(
                        TextSelection.near(tr.doc.resolve(resolved.pos)),
                    );
                }
            }

            return true;
        })
        .run();
}
