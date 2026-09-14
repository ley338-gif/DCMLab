<script setup lang="ts">
import ToolCard from '@/components/lesson/ToolCard.vue';

export type ToolbarTool = {
    slug: string;
    name: string;
    purpose: string | null;
    example: string | null;
    is_new: boolean;
};

defineProps<{ tools: ToolbarTool[] }>();
</script>

<template>
    <div v-if="tools.length" class="tool-grid">
        <ToolCard
            v-for="tool in tools"
            :key="tool.slug"
            :slug="tool.slug"
            :name="tool.name"
            :purpose="tool.purpose"
            :example="tool.example"
            :is-new="tool.is_new"
        />
    </div>
</template>
