<script setup lang="ts">
import { GripVertical, Plus } from '@lucide/vue';
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3';
import { computed } from 'vue';
import { resolveBlockDefinition } from '@/lib/richContent/blockDefinitions';
import { insertBlockAtAnchor } from '@/lib/richContent/insertionAnchor';

/**
 * Ein generisches NodeView-Chrome fuer alle chrome-tragenden Blocktypen
 * (`callout`, `self_check`, `dicom_tag_table`, `code_block` -- siehe
 * tiptapExtensions.ts) -- EIN Wrapper statt vier eigenen Komponenten, weil
 * sich Label/Icon bereits ueber `resolveBlockDefinition()` ergeben.
 * Bewusst NICHT fuer `table`: TipTaps eigene Table-NodeView bleibt
 * unveraendert, um Zellenauswahl/Resizing nicht zu riskieren (Plan §14).
 *
 * Diese Wrapper-DOM ist fuer ProseMirrors Serialisierung unsichtbar --
 * `getJSON()`/`fromTipTap()` lesen ausschliesslich den eigentlichen Knoten,
 * nie das umgebende NodeView-Markup (TipTap/ProseMirror-Grundgarantie).
 */
const props = defineProps(nodeViewProps);

const definition = computed(() =>
    resolveBlockDefinition({
        type: props.node.type.name,
        attrs: props.node.attrs,
    }),
);

function insertParagraphAfter(): void {
    const pos = props.getPos();

    if (typeof pos !== 'number') {
        return;
    }

    insertBlockAtAnchor(
        props.editor,
        { type: 'paragraph' },
        { kind: 'afterPos', pos: pos + props.node.nodeSize },
    );
}
</script>

<template>
    <NodeViewWrapper
        class="rich-content-block-chrome"
        :class="{ 'is-selected': selected }"
        :data-block-type="node.type.name"
    >
        <div class="rich-content-block-chrome-header" contenteditable="false">
            <component
                :is="GripVertical"
                class="rich-content-block-chrome-handle"
                aria-hidden="true"
            />
            <component
                :is="definition?.icon"
                v-if="definition?.icon"
                class="rich-content-block-chrome-icon"
                aria-hidden="true"
            />
            <span class="rich-content-block-chrome-label">{{
                definition?.label ?? node.type.name
            }}</span>
        </div>
        <NodeViewContent class="rich-content-block-chrome-content" />
        <button
            type="button"
            class="rich-content-block-chrome-add"
            contenteditable="false"
            aria-label="Block danach einfügen"
            title="Block danach einfügen"
            @click="insertParagraphAfter"
        >
            <component :is="Plus" class="size-3.5" aria-hidden="true" />
        </button>
    </NodeViewWrapper>
</template>
