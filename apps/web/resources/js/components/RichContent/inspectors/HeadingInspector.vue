<script setup lang="ts">
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * Ueberschriften sind im Editor auf die Ebenen 2-4 beschraenkt (bestehende
 * Einschraenkung aus `richContentExtensions()`, hier nicht erweitert --
 * siehe Plan §13).
 */
const props = defineProps<{
    node: ProseMirrorNode;
    updateAttrs: (partial: Record<string, unknown>) => void;
    readonly?: boolean;
}>();

const level = computed(() => String(props.node.attrs.level ?? 2));

function setLevel(value: unknown): void {
    props.updateAttrs({ level: Number(value) });
}
</script>

<template>
    <div class="rich-content-inspector-field">
        <label class="rich-content-inspector-label">Ebene</label>
        <Select
            :model-value="level"
            :disabled="readonly"
            @update:model-value="setLevel"
        >
            <SelectTrigger size="sm" class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="2">Ebene 2</SelectItem>
                <SelectItem value="3">Ebene 3</SelectItem>
                <SelectItem value="4">Ebene 4</SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
