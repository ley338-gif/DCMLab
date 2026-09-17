<script setup lang="ts">
import type { Node as ProseMirrorNode } from '@tiptap/pm/model';
import { computed } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    node: ProseMirrorNode;
    updateAttrs: (partial: Record<string, unknown>) => void;
    readonly?: boolean;
}>();

const summary = computed(() => String(props.node.attrs.summary ?? ''));

function setSummary(value: string | number): void {
    props.updateAttrs({ summary: String(value) });
}
</script>

<template>
    <div class="rich-content-inspector-field">
        <label class="rich-content-inspector-label"
            >Text für „Antwort anzeigen“</label
        >
        <Input
            :model-value="summary"
            :disabled="readonly"
            @update:model-value="setSummary"
        />
    </div>
</template>
