<script setup lang="ts">
import { trans } from '@/lib/trans';
import type { TocEntry } from '@/composables/useLessonToc';

defineProps<{ entries: TocEntry[]; activeId: string | null }>();
const emit = defineEmits<{ select: [id: string] }>();
</script>

<template>
    <nav
        v-if="entries.length"
        class="lesson-toc"
        :aria-label="trans('In dieser Lektion')"
    >
        <p class="lesson-toc-heading">{{ trans('In dieser Lektion') }}</p>
        <ul>
            <li
                v-for="entry in entries"
                :key="entry.id"
                :class="{ 'is-sub': entry.level === 3 }"
            >
                <button
                    type="button"
                    class="lesson-toc-link"
                    :class="{ 'is-active': entry.id === activeId }"
                    @click="emit('select', entry.id)"
                >
                    {{ entry.text }}
                </button>
            </li>
        </ul>
    </nav>
</template>
