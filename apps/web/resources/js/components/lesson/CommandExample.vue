<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { useClipboardCopy } from '@/composables/useClipboardCopy';
import { trans } from '@/lib/trans';

const props = defineProps<{ command: string }>();

const { copiedKey, copy } = useClipboardCopy();
</script>

<template>
    <div class="lesson-console">
        <div class="lesson-block-bar">
            <button
                type="button"
                class="lesson-copy-btn"
                :class="{ 'is-copied': copiedKey === command }"
                :aria-label="trans('Befehl kopieren')"
                @click="copy(props.command)"
            >
                <Check
                    v-if="copiedKey === command"
                    class="size-3.5"
                    aria-hidden="true"
                />
                <Copy v-else class="size-3.5" aria-hidden="true" />
                <span class="lesson-copy-btn-label">{{
                    copiedKey === command ? trans('Kopiert') : trans('Kopieren')
                }}</span>
            </button>
        </div>
        <pre><code><span class="lesson-line lesson-line-prompt">$ {{ command }}</span></code></pre>
    </div>
</template>
