<script setup lang="ts">
import { PanelRight } from '@lucide/vue';
import GlobalHeader from '@/components/GlobalHeader.vue';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { trans } from '@/lib/trans';
</script>

<template>
    <div class="lesson-app">
        <GlobalHeader>
            <template #mobileExtra>
                <slot name="sidebar" />
            </template>

            <template #actions>
                <Sheet>
                    <SheetTrigger
                        class="lesson-app-icon-btn lesson-app-toc-trigger"
                        :aria-label="trans('In dieser Lektion')"
                    >
                        <PanelRight class="size-4" aria-hidden="true" />
                    </SheetTrigger>
                    <SheetContent side="right" class="lesson-app-drawer">
                        <SheetHeader>
                            <SheetTitle>{{
                                trans('In dieser Lektion')
                            }}</SheetTitle>
                        </SheetHeader>
                        <div class="lesson-app-drawer-body">
                            <slot name="toc" />
                        </div>
                    </SheetContent>
                </Sheet>
            </template>
        </GlobalHeader>

        <div class="lesson-app-body">
            <aside class="lesson-app-col lesson-app-col-sidebar">
                <slot name="sidebar" />
            </aside>

            <main class="lesson-app-col lesson-app-col-main">
                <slot />
            </main>

            <aside class="lesson-app-col lesson-app-col-toc">
                <slot name="toc" />
            </aside>
        </div>

        <Toaster />
    </div>
</template>
