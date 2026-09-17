<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { onBeforeUnmount, onMounted, shallowRef } from 'vue';
import {
    resolveBlockDefinition,
    type RichContentBlockDefinition,
} from '@/lib/richContent/blockDefinitions';
import { resolveSelectedBlock } from '@/lib/richContent/insertionAnchor';
import type { UnsupportedEditorNodeError } from '@/lib/richContent/RichContentEditorAdapter';
import type { GlossaryTermOption } from '@/lib/richContent/slashCommand';
import type { RichContentDocument } from '@/types/richContent';
import RichContentEditor from './RichContentEditor.vue';
import RichContentToolbox from './RichContentToolbox.vue';

/**
 * Toolbox + Editor + Inspector als EIN Layout (Plan §7/§8) -- der Editor
 * selbst bleibt unveraendert `RichContentEditor.vue`, wiederverwendet ueber
 * dessen bestehendes `defineExpose({ editor })`. Alle Workbench-eigenen
 * Zustaende (hier: `selectedBlock`) leben bewusst NUR in diesem
 * `<script setup>` (component-instance-scoped), nie auf Modulebene --
 * mehrere gleichzeitige Workbench-Instanzen auf einer Seite (z. B. Node:
 * Briefing/Hints/Write-up) duerfen sich nie gegenseitig beeinflussen
 * (Plan §16).
 */
const props = withDefaults(
    defineProps<{
        modelValue: RichContentDocument;
        editable?: boolean;
        glossaryTerms?: GlossaryTermOption[];
    }>(),
    { editable: true, glossaryTerms: () => [] },
);

const emit = defineEmits<{
    'update:modelValue': [RichContentDocument];
    'unsupported-node': [UnsupportedEditorNodeError];
}>();

const editorRef = shallowRef<InstanceType<typeof RichContentEditor> | null>(
    null,
);

export type SelectedBlock = {
    pos: number;
    node: ProseMirrorNode;
    definition: RichContentBlockDefinition | undefined;
};

const selectedBlock = shallowRef<SelectedBlock | null>(null);

function currentEditor(): Editor | undefined {
    return (editorRef.value as unknown as { editor?: Editor } | null)?.editor;
}

/**
 * Wird bei JEDER Transaktion neu aufgerufen (nicht nur bei
 * `selectionUpdate`), weil eine Inhaltsaenderung anderswo im Dokument eine
 * zuvor aufgeloeste Position ungueltig machen kann, auch ohne ein neues
 * Selektionsereignis (Plan §12). Liest die Selektion jedes Mal frisch --
 * niemals eine `pos` aus einem frueheren Aufruf wiederverwenden.
 */
function recomputeSelectedBlock(): void {
    const editor = currentEditor();

    if (!editor) {
        selectedBlock.value = null;
        return;
    }

    const block = resolveSelectedBlock(editor);

    selectedBlock.value = block
        ? {
              pos: block.pos,
              node: block.node,
              definition: resolveBlockDefinition({
                  type: block.node.type.name,
                  attrs: block.node.attrs,
              }),
          }
        : null;
}

onMounted(() => {
    const editor = currentEditor();
    editor?.on('transaction', recomputeSelectedBlock);
    recomputeSelectedBlock();
});

onBeforeUnmount(() => {
    currentEditor()?.off('transaction', recomputeSelectedBlock);
});

defineExpose({ selectedBlock, currentEditor });
</script>

<template>
    <div class="rich-content-workbench">
        <div class="rich-content-workbench-toolbox">
            <RichContentToolbox
                :editor="currentEditor()"
                :disabled="!props.editable"
            />
        </div>
        <div class="rich-content-workbench-editor">
            <RichContentEditor
                ref="editorRef"
                :model-value="props.modelValue"
                :editable="props.editable"
                :glossary-terms="props.glossaryTerms"
                @update:model-value="emit('update:modelValue', $event)"
                @unsupported-node="emit('unsupported-node', $event)"
            />
        </div>
        <div class="rich-content-workbench-inspector">
            <p
                v-if="!selectedBlock"
                class="rich-content-workbench-inspector-empty"
            >
                Kein Block ausgewählt.
            </p>
            <p v-else class="rich-content-workbench-inspector-empty">
                {{
                    selectedBlock.definition?.label ??
                    selectedBlock.node.type.name
                }}
            </p>
        </div>
    </div>
</template>
