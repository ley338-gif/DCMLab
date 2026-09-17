<script setup lang="ts">
import type { Editor } from '@tiptap/core';

/**
 * Bewusst KEINE eigene NodeView/Chrome fuer `table` (Plan §14) -- diese
 * Operationen rufen ausschliesslich TipTaps eigene, bereits installierte
 * `@tiptap/extension-table`-Kommandos auf. Diese Kommandos ermitteln die
 * betroffene Tabelle/Zelle selbst ueber die AKTUELLE ProseMirror-Selektion
 * (nicht ueber eine hier uebergebene Position) -- ein Klick auf einen
 * dieser Buttons bewegt den DOM-Fokus zwar auf den Inspector, aendert aber
 * NICHT die ProseMirror-Selektion selbst, `.focus()` im Chain stellt nur
 * den sichtbaren Cursor wieder her. Dadurch trifft `.addRowAfter()` etc.
 * zuverlaessig dieselbe Tabelle/Zelle, in der der Cursor zuletzt stand --
 * verifiziert in TableInspector.spec.ts mit mehreren Tabellen im selben
 * Dokument (Betreiber-Korrektur: "verify real TipTap behavior ... target
 * the currently selected table safely").
 */
const props = defineProps<{
    editor: Editor;
    readonly?: boolean;
}>();

function run(command: (editor: Editor) => void): void {
    if (props.readonly) {
        return;
    }

    command(props.editor);
}
</script>

<template>
    <div class="rich-content-inspector-table-ops">
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().addRowBefore().run())"
        >
            Zeile davor einfügen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().addRowAfter().run())"
        >
            Zeile danach einfügen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().deleteRow().run())"
        >
            Zeile löschen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().addColumnBefore().run())"
        >
            Spalte davor einfügen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().addColumnAfter().run())"
        >
            Spalte danach einfügen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().deleteColumn().run())"
        >
            Spalte löschen
        </button>
        <button
            type="button"
            class="rich-content-inspector-op"
            :disabled="readonly"
            @click="run((e) => e.chain().focus().toggleHeaderRow().run())"
        >
            Kopfzeile umschalten
        </button>
    </div>
</template>
