<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import CommandExample from '@/components/lesson/CommandExample.vue';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { trans } from '@/lib/trans';

const props = defineProps<{
    slug: string;
    name: string;
    purpose: string | null;
    example: string | null;
    isNew: boolean;
}>();

const role = props.name.endsWith('scu')
    ? 'SCU'
    : props.name.endsWith('scp')
      ? 'SCP'
      : null;

// Ein paar Registry-Eintraege (z. B. Orthanc: "laeuft bereits") sind eine
// Statusbeschreibung, kein Befehl zum Abtippen -- ein echter Befehl beginnt
// immer mit dem Namen des Werkzeugs selbst (siehe content/tools/de.yml).
const isRunnableCommand =
    props.example?.toLowerCase().startsWith(props.name.toLowerCase()) ?? false;

const open = ref(false);
</script>

<template>
    <Collapsible v-model:open="open" as="article" class="tool-card">
        <div class="tool-card-head">
            <code class="tool-card-name">{{ name }}</code>
            <div class="tool-card-badges">
                <Badge v-if="isNew" class="text-xs">{{ trans('NEU') }}</Badge>
                <Badge v-if="role" variant="outline" class="text-xs">{{
                    role
                }}</Badge>
            </div>
        </div>

        <p v-if="purpose" class="tool-card-purpose">{{ purpose }}</p>

        <template v-if="example && isRunnableCommand">
            <CollapsibleTrigger class="tool-card-trigger">
                {{
                    open
                        ? trans('Beispiel ausblenden')
                        : trans('Beispiel anzeigen')
                }}
                <ChevronDown
                    class="size-3.5 transition-transform"
                    :class="{ 'rotate-180': open }"
                />
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CommandExample :command="example" />
            </CollapsibleContent>
        </template>

        <p v-else-if="example" class="tool-card-status">{{ example }}</p>
    </Collapsible>
</template>
