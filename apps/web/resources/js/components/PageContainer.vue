<script setup lang="ts">
withDefaults(defineProps<{ wide?: boolean }>(), { wide: false });
</script>

<template>
    <main
        class="mx-auto w-full px-6 py-10"
        :class="wide ? 'max-w-[1600px]' : 'max-w-5xl'"
    >
        <slot />
    </main>
</template>
