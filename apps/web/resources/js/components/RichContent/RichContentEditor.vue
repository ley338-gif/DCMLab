<script setup lang="ts">
/**
 * Gemeinsamer Rich-Content-Editor fuer Lesson/Node (CMS-7b/7c) -- kennt
 * nach aussen ausschliesslich das DCMLab-v1-Dokumentformat
 * (`RichContentDocument`, ADR 0111/0112/0114). TipTap ist ein reines
 * internes Implementierungsdetail: `modelValue`/`update:modelValue` sind
 * nie TipTap-JSON, siehe `RichContentEditorAdapter`.
 *
 * Seit CMS-7d.3 (ADR 0118) tatsaechlich an `lessons.rich_content`/
 * `nodes.rich_content` angeschlossen (`Author/LessonEditor.vue`,
 * `Studio/Nodes/Edit.vue`) -- vorher (CMS-7b/7c) bewusst isoliert
 * gehalten, ohne Controller-/Publisher-Anbindung.
 */
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { ref, watch } from 'vue';
import {
    fromTipTap,
    toTipTap,
    UnsupportedEditorNodeError,
} from '@/lib/richContent/RichContentEditorAdapter';
import type { GlossaryTermOption } from '@/lib/richContent/slashCommand';
import { richContentExtensions } from '@/lib/richContent/tiptapExtensions';
import { trans } from '@/lib/trans';
import type { RichContentDocument } from '@/types/richContent';

const props = withDefaults(
    defineProps<{
        modelValue: RichContentDocument;
        editable?: boolean;
        /**
         * Fuer das "/glossary"-Slash-Kommando (ADR 0114) -- leer, wenn der
         * Aufrufer (noch) keine Glossarliste uebergibt.
         */
        glossaryTerms?: GlossaryTermOption[];
    }>(),
    { editable: true, glossaryTerms: () => [] },
);

const emit = defineEmits<{
    'update:modelValue': [RichContentDocument];
    'unsupported-node': [UnsupportedEditorNodeError];
}>();

const loadError = ref<UnsupportedEditorNodeError | null>(null);

function safeToTipTap(doc: RichContentDocument) {
    try {
        const tiptapDoc = toTipTap(doc);
        loadError.value = null;

        return tiptapDoc;
    } catch (error) {
        if (!(error instanceof UnsupportedEditorNodeError)) {
            throw error;
        }

        loadError.value = error;
        emit('unsupported-node', error);

        return null;
    }
}

const editor = useEditor({
    content: safeToTipTap(props.modelValue) ?? undefined,
    extensions: richContentExtensions(props.glossaryTerms),
    editable: props.editable,
    onUpdate: ({ editor: updatedEditor }) => {
        emit('update:modelValue', fromTipTap(updatedEditor.getJSON()));
    },
});

// Laedt den Editor-Inhalt neu, wenn sich modelValue von aussen aendert
// (z. B. Wechsel des bearbeiteten Dokuments) -- nicht aber fuer die
// eigene onUpdate-Ruecklaufschleife, sonst wuerde jeder Tastendruck den
// Editor-Zustand (Cursor-Position) unter sich selbst zuruecksetzen.
watch(
    () => props.modelValue,
    (doc) => {
        if (!editor.value) {
            return;
        }

        const currentAsDcmlab = fromTipTap(editor.value.getJSON());

        if (JSON.stringify(currentAsDcmlab) === JSON.stringify(doc)) {
            return;
        }

        const tiptapDoc = safeToTipTap(doc);

        if (tiptapDoc !== null) {
            editor.value.commands.setContent(tiptapDoc);
        }
    },
);

// Fuer Toolbar-Buttons einer umgebenden Seite (z. B. "Fett", "Undo") und
// fuer Tests -- der Editor selbst bleibt trotzdem ein internes Detail,
// niemand ausserhalb dieser Komponente liest hier TipTap-JSON aus.
defineExpose({ editor });
</script>

<template>
    <div class="rich-content-editor">
        <p v-if="loadError" class="text-destructive text-sm">
            {{
                trans(
                    'Dieses Dokument enthält einen Block, den der Editor noch nicht unterstützt: :type',
                    { type: loadError.nodeType },
                )
            }}
        </p>
        <EditorContent v-else :editor="editor" />
    </div>
</template>
