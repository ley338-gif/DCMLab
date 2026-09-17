<script setup lang="ts">
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    node: ProseMirrorNode;
    updateAttrs: (partial: Record<string, unknown>) => void;
    readonly?: boolean;
}>();

const kind = computed(() => String(props.node.attrs.kind ?? 'info'));
const title = computed(() => (props.node.attrs.title as string | null) ?? '');

function setKind(value: unknown): void {
    props.updateAttrs({ kind: value });
}

function setTitle(value: string | number): void {
    const text = String(value);
    props.updateAttrs({ title: text === '' ? null : text });
}
</script>

<template>
    <div class="rich-content-inspector-field">
        <label class="rich-content-inspector-label">Art</label>
        <Select
            :model-value="kind"
            :disabled="readonly"
            @update:model-value="setKind"
        >
            <SelectTrigger size="sm" class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="info">Info</SelectItem>
                <SelectItem value="warning">Warnung</SelectItem>
            </SelectContent>
        </Select>
    </div>
    <div class="rich-content-inspector-field">
        <label class="rich-content-inspector-label">Titel</label>
        <Input
            :model-value="title"
            :disabled="readonly"
            placeholder="(kein Titel)"
            @update:model-value="setTitle"
        />
    </div>
</template>
