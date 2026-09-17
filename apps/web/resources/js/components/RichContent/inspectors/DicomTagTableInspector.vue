<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { resolveSelectedBlock } from '@/lib/richContent/insertionAnchor';

/**
 * `dicomTagRow`-Zeilen sind Atome ohne eigene Position-tragende Attribute
 * am `dicomTagTable`-Knoten selbst -- Bearbeitung passiert deshalb NICHT
 * ueber das generische `updateAttrs` (das setzt Attribute des
 * AUSGEWAEHLTEN Knotens selbst), sondern ueber gezielte, bei JEDER
 * Operation frisch aufgeloeste Transaktionen auf die Kind-Zeilen (Plan
 * §13). `resolveSelectedBlock()` wird deshalb hier selbst nochmal
 * aufgerufen statt den `node`/`pos`-Props zu vertrauen, die zwischen zwei
 * Zeilenoperationen veralten koennten.
 */
const props = defineProps<{
    editor: Editor;
    node: ProseMirrorNode;
    readonly?: boolean;
}>();

type RowAttrs = { tag: string; keyword: string; vr: string; value: string };
type Row = { offset: number; attrs: RowAttrs };

function freshTable(): { pos: number; node: ProseMirrorNode } | null {
    const block = resolveSelectedBlock(props.editor);

    if (!block || block.node.type.name !== 'dicomTagTable') {
        return null;
    }

    return block;
}

const rows = computed<Row[]>(() => {
    // Liest bewusst `props.node` (nicht `freshTable()`), damit die Liste
    // reaktiv auf Vue-Prop-Aenderungen reagiert -- die Positionen selbst
    // werden je Operation trotzdem frisch aufgeloest (siehe oben).
    const table = props.node;
    const list: Row[] = [];
    let offset = 0;

    table.forEach((child) => {
        list.push({
            offset,
            attrs: {
                tag: String(child.attrs.tag ?? ''),
                keyword: String(child.attrs.keyword ?? ''),
                vr: String(child.attrs.vr ?? ''),
                value: String(child.attrs.value ?? ''),
            },
        });
        offset += child.nodeSize;
    });

    return list;
});

/**
 * Bewusst OHNE `.focus()`: wird bei JEDEM Tastendruck in einem Zellenfeld
 * aufgerufen -- `.focus()` wuerde den DOM-Fokus vom gerade getippten Feld
 * auf den Editor zurueckreissen und fortlaufendes Tippen unmoeglich machen
 * (Betreiber-Befund aus der PR-Pruefung). `addRow()`/`removeRow()` sind
 * dagegen einzelne Klicks auf einen Button, kein Tippen -- dort bleibt
 * `.focus()`, um den sichtbaren Cursor sinnvoll zur Tabelle zurueckzuholen.
 */
function updateRow(
    rowOffset: number,
    field: keyof RowAttrs,
    value: string,
): void {
    if (props.readonly) {
        return;
    }

    const fresh = freshTable();

    if (!fresh) {
        return;
    }

    const rowPos = fresh.pos + 1 + rowOffset;

    props.editor
        .chain()
        .command(({ tr }) => {
            const rowNode = tr.doc.nodeAt(rowPos);

            if (!rowNode) {
                return false;
            }

            tr.setNodeMarkup(rowPos, undefined, {
                ...rowNode.attrs,
                [field]: value,
            });

            return true;
        })
        .run();
}

function addRow(): void {
    if (props.readonly) {
        return;
    }

    const fresh = freshTable();

    if (!fresh) {
        return;
    }

    const insertPos = fresh.pos + fresh.node.nodeSize - 1;

    props.editor
        .chain()
        .focus()
        .command(({ tr, state }) => {
            const rowType = state.schema.nodes.dicomTagRow;
            tr.insert(
                insertPos,
                rowType.create({ tag: '', keyword: '', vr: '', value: '' }),
            );

            return true;
        })
        .run();
}

function removeRow(rowOffset: number): void {
    if (props.readonly) {
        return;
    }

    const fresh = freshTable();

    if (!fresh || fresh.node.childCount <= 1) {
        return;
    }

    const rowPos = fresh.pos + 1 + rowOffset;

    props.editor
        .chain()
        .focus()
        .command(({ tr }) => {
            const rowNode = tr.doc.nodeAt(rowPos);

            if (!rowNode) {
                return false;
            }

            tr.delete(rowPos, rowPos + rowNode.nodeSize);

            return true;
        })
        .run();
}
</script>

<template>
    <div class="rich-content-inspector-dicom-rows">
        <div
            v-for="row in rows"
            :key="row.offset"
            class="rich-content-inspector-dicom-row"
        >
            <Input
                :model-value="row.attrs.tag"
                placeholder="Tag"
                :disabled="readonly"
                @update:model-value="
                    (v) => updateRow(row.offset, 'tag', String(v))
                "
            />
            <Input
                :model-value="row.attrs.keyword"
                placeholder="Keyword"
                :disabled="readonly"
                @update:model-value="
                    (v) => updateRow(row.offset, 'keyword', String(v))
                "
            />
            <Input
                :model-value="row.attrs.vr"
                placeholder="VR"
                :disabled="readonly"
                @update:model-value="
                    (v) => updateRow(row.offset, 'vr', String(v))
                "
            />
            <Input
                :model-value="row.attrs.value"
                placeholder="Wert"
                :disabled="readonly"
                @update:model-value="
                    (v) => updateRow(row.offset, 'value', String(v))
                "
            />
            <button
                type="button"
                class="rich-content-inspector-row-remove"
                :disabled="readonly || rows.length <= 1"
                aria-label="Zeile entfernen"
                title="Zeile entfernen"
                @click="removeRow(row.offset)"
            >
                ✕
            </button>
        </div>
        <button
            type="button"
            class="rich-content-inspector-row-add"
            :disabled="readonly"
            @click="addRow"
        >
            + Zeile hinzufügen
        </button>
    </div>
</template>
