<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { computed } from 'vue';
import { resolveSelectedBlock } from '@/lib/richContent/insertionAnchor';
import CalloutInspector from './inspectors/CalloutInspector.vue';
import CodeBlockInspector from './inspectors/CodeBlockInspector.vue';
import DicomTagTableInspector from './inspectors/DicomTagTableInspector.vue';
import HeadingInspector from './inspectors/HeadingInspector.vue';
import SelfCheckInspector from './inspectors/SelfCheckInspector.vue';
import TableInspector from './inspectors/TableInspector.vue';

export type InspectorSelectedBlock = {
    pos: number;
    node: ProseMirrorNode;
    definition?: { label: string };
};

const props = defineProps<{
    editor: Editor | undefined;
    selectedBlock: InspectorSelectedBlock | null;
    readonly?: boolean;
}>();

/**
 * Dispatch nach dem ROHEN `node.type.name` -- bewusst getrennt von
 * `resolveBlockDefinition()` (das ist Chrome-/Toolbox-Identitaet inkl.
 * Varianten-Unterscheidung): welche Inspector-Komponente angezeigt wird,
 * haengt nicht von der Attrs-Variante ab (ein Code-Block zeigt IMMER
 * denselben CodeBlockInspector, unabhaengig von `variant`; ein `heading`
 * IMMER denselben HeadingInspector, unabhaengig vom Level). Absatz,
 * Zitat, Listen und Trennlinie haben bewusst keinen Eintrag -- Identitaet
 * nur, keine Einstellungen (Plan §13).
 */
const component = computed(() => {
    switch (props.selectedBlock?.node.type.name) {
        case 'heading':
            return HeadingInspector;
        case 'callout':
            return CalloutInspector;
        case 'codeBlock':
            return CodeBlockInspector;
        case 'dicomTagTable':
            return DicomTagTableInspector;
        case 'selfCheck':
            return SelfCheckInspector;
        case 'table':
            return TableInspector;
        default:
            return null;
    }
});

/**
 * Loest die aktuelle Position IMMER frisch ueber `resolveSelectedBlock()`
 * auf statt der `selectedBlock`-Prop zu vertrauen (Plan §13: "re-resolves
 * ... immediately before writing"). Ein Klick auf ein Inspector-Steuerelement
 * bewegt den DOM-Fokus, aendert aber nicht die ProseMirror-Selektion selbst
 * -- die frische Aufloesung liefert deshalb zuverlaessig denselben Block,
 * es sei denn, er hat sich (durch eine andere Ursache) tatsaechlich
 * geaendert, in welchem Fall bewusst NICHT geschrieben wird, um nie einen
 * anderen als den angezeigten Block zu treffen.
 */
function updateAttrs(partial: Record<string, unknown>): void {
    if (!props.editor || props.readonly || !props.selectedBlock) {
        return;
    }

    const expectedType = props.selectedBlock.node.type.name;
    const fresh = resolveSelectedBlock(props.editor);

    if (!fresh || fresh.node.type.name !== expectedType) {
        return;
    }

    props.editor
        .chain()
        .focus()
        .command(({ tr }) => {
            tr.setNodeMarkup(fresh.pos, undefined, {
                ...fresh.node.attrs,
                ...partial,
            });

            return true;
        })
        .run();
}
</script>

<template>
    <div class="rich-content-inspector">
        <p v-if="!selectedBlock" class="rich-content-inspector-empty">
            Kein Block ausgewählt.
        </p>
        <template v-else>
            <h3 class="rich-content-inspector-title">
                {{
                    selectedBlock.definition?.label ??
                    selectedBlock.node.type.name
                }}
            </h3>
            <component
                :is="component"
                v-if="component && editor"
                :node="selectedBlock.node"
                :editor="editor"
                :readonly="readonly"
                :update-attrs="updateAttrs"
            />
            <p v-else class="rich-content-inspector-empty">
                Keine Einstellungen für diesen Block.
            </p>
        </template>
    </div>
</template>
