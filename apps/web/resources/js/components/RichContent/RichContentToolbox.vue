<script setup lang="ts">
import type { Editor } from '@tiptap/core';
import { computed } from 'vue';
import {
    BLOCK_GROUP_LABELS,
    toolboxBlockDefinitions,
    type RichContentBlockDefinition,
} from '@/lib/richContent/blockDefinitions';
import { insertBlockAtAnchor } from '@/lib/richContent/insertionAnchor';

/**
 * Rendert genau die Definitionen mit `showInToolbox !== false`, gruppiert
 * nach GRUNDELEMENTE/DCMLAB/STRUKTUR (Betreiber-Vorgabe) -- Slash-Menue und
 * Chrome nutzen dieselben `blockDefinitions`, sehen aber zusaetzlich
 * Definitionen, die hier bewusst nicht als Button erscheinen (siehe
 * blockDefinitions.ts fuer die Begruendung je Ausnahme).
 */
const props = defineProps<{ editor: Editor | undefined; disabled?: boolean }>();

const GROUP_ORDER = ['grundelemente', 'dcmlab', 'struktur'] as const;

const groups = computed(() => {
    const byGroup = new Map<
        RichContentBlockDefinition['group'],
        RichContentBlockDefinition[]
    >();

    for (const definition of toolboxBlockDefinitions()) {
        if (
            definition.isAvailable &&
            props.editor &&
            !definition.isAvailable(props.editor)
        ) {
            continue;
        }

        const items = byGroup.get(definition.group) ?? [];
        items.push(definition);
        byGroup.set(definition.group, items);
    }

    return GROUP_ORDER.map((group) => ({
        group,
        label: BLOCK_GROUP_LABELS[group],
        items: byGroup.get(group) ?? [],
    })).filter((entry) => entry.items.length > 0);
});

function insert(definition: RichContentBlockDefinition): void {
    if (!props.editor || props.disabled) {
        return;
    }

    insertBlockAtAnchor(props.editor, definition.createNode(), {
        kind: 'selection',
    });
}
</script>

<template>
    <nav class="rich-content-toolbox" aria-label="Bausteine einfügen">
        <div
            v-for="group in groups"
            :key="group.group"
            class="rich-content-toolbox-group"
        >
            <h3 class="rich-content-toolbox-group-title">{{ group.label }}</h3>
            <button
                v-for="item in group.items"
                :key="item.id"
                type="button"
                class="rich-content-toolbox-item"
                :disabled="disabled"
                :title="item.description"
                @click="insert(item)"
            >
                <component
                    :is="item.icon"
                    class="rich-content-toolbox-item-icon"
                    aria-hidden="true"
                />
                <span>{{ item.label }}</span>
            </button>
        </div>
    </nav>
</template>
