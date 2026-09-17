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

/**
 * `diagram`/`mermaid` sind bewusst hier waehlbar, obwohl sie keinen eigenen
 * Toolbox-/Slash-Eintrag haben (Plan §13) -- Schema/Adapter unterstuetzen
 * sie bereits (nur bisher import-only), der Inspector ist der erste Ort,
 * an dem sie ohne direkte JSON-Bearbeitung waehlbar werden.
 */
const props = defineProps<{
    node: ProseMirrorNode;
    updateAttrs: (partial: Record<string, unknown>) => void;
    readonly?: boolean;
}>();

const variant = computed(() => String(props.node.attrs.variant ?? 'terminal'));
const language = computed(() => String(props.node.attrs.language ?? ''));

function setVariant(value: unknown): void {
    props.updateAttrs({ variant: value });
}

function setLanguage(value: string | number): void {
    const text = String(value);
    props.updateAttrs({ language: text === '' ? null : text });
}
</script>

<template>
    <div class="rich-content-inspector-field">
        <label class="rich-content-inspector-label">Variante</label>
        <Select
            :model-value="variant"
            :disabled="readonly"
            @update:model-value="setVariant"
        >
            <SelectTrigger size="sm" class="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="code">Code</SelectItem>
                <SelectItem value="terminal">Terminal</SelectItem>
                <SelectItem value="console">Konsole</SelectItem>
                <SelectItem value="dicom_dump">DICOM Dump</SelectItem>
                <SelectItem value="diagram">Diagramm</SelectItem>
                <SelectItem value="mermaid">Mermaid</SelectItem>
            </SelectContent>
        </Select>
    </div>
    <div v-if="variant === 'code'" class="rich-content-inspector-field">
        <label class="rich-content-inspector-label">Sprache</label>
        <Input
            :model-value="language"
            :disabled="readonly"
            placeholder="z. B. python"
            @update:model-value="setLanguage"
        />
    </div>
</template>
