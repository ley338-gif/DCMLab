<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { trans } from '@/lib/trans';
import { index as studioIndex } from '@/routes/studio';
import {
    store as storeTemplate,
    update as updateTemplate,
} from '@/routes/studio/sandbox-templates';

type Status = 'draft' | 'published';

type SandboxTemplateRow = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    runtime_provider: string;
    status: Status;
};

const props = defineProps<{
    templates: SandboxTemplateRow[];
    can_manage: boolean;
}>();

const statusLabels: Record<Status, string> = {
    draft: trans('Entwurf'),
    published: trans('Freigegeben'),
};

const editing = ref<number | null>(null);
const editForm = ref<{ name: string; description: string; status: Status }>({
    name: '',
    description: '',
    status: 'draft',
});

const newTemplate = ref({ slug: '', name: '', description: '' });

function startEditing(template: SandboxTemplateRow) {
    editing.value = template.id;
    editForm.value = {
        name: template.name,
        description: template.description ?? '',
        status: template.status,
    };
}

function saveTemplate(id: number) {
    router.patch(updateTemplate.url({ sandboxTemplate: id }), editForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function createTemplate() {
    if (
        newTemplate.value.slug.trim() === '' ||
        newTemplate.value.name.trim() === ''
    ) {
        return;
    }

    router.post(storeTemplate.url(), newTemplate.value, {
        preserveScroll: true,
        onSuccess: () => {
            newTemplate.value = { slug: '', name: '', description: '' };
        },
    });
}
</script>

<template>
    <Head :title="trans('Sandbox-Vorlagen')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Studio'), href: studioIndex() },
                { title: trans('Sandbox-Vorlagen'), href: '' },
            ]"
        />

        <h1 class="mb-2 text-2xl font-semibold">
            {{ trans('Sandbox-Vorlagen') }}
        </h1>
        <p class="text-muted-foreground mb-6 text-sm">
            {{
                trans(
                    'Nur freigegebene Vorlagen stehen Lektionen mit Spielwiese tatsächlich zur Verfügung.',
                )
            }}
        </p>

        <Card v-if="props.can_manage" class="mb-6">
            <CardHeader>
                <CardTitle>{{ trans('Neue Vorlage anlegen') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex-1 space-y-1.5">
                    <Label>{{ trans('Slug') }}</Label>
                    <Input
                        v-model="newTemplate.slug"
                        :placeholder="trans('z. B. dicom-advanced-tools')"
                    />
                </div>
                <div class="flex-1 space-y-1.5">
                    <Label>{{ trans('Name') }}</Label>
                    <Input v-model="newTemplate.name" />
                </div>
                <div class="flex-1 space-y-1.5">
                    <Label>{{ trans('Beschreibung') }}</Label>
                    <Input v-model="newTemplate.description" />
                </div>
                <Button @click="createTemplate">{{ trans('Anlegen') }}</Button>
            </CardContent>
        </Card>

        <div class="flex flex-col gap-3">
            <p
                v-if="props.templates.length === 0"
                class="text-muted-foreground text-sm"
            >
                {{ trans('Noch keine Sandbox-Vorlage vorhanden.') }}
            </p>

            <Card v-for="template in props.templates" :key="template.id">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base">{{
                            template.name
                        }}</CardTitle>
                        <p class="text-muted-foreground text-sm">
                            {{ template.slug }} ·
                            {{ template.runtime_provider }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                template.status === 'published'
                                    ? 'default'
                                    : 'secondary'
                            "
                        >
                            {{ statusLabels[template.status] }}
                        </Badge>
                        <Button
                            v-if="props.can_manage"
                            variant="outline"
                            size="sm"
                            @click="
                                editing === template.id
                                    ? (editing = null)
                                    : startEditing(template)
                            "
                        >
                            {{
                                editing === template.id
                                    ? trans('Schließen')
                                    : trans('Bearbeiten')
                            }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent
                    v-if="editing === template.id"
                    class="flex flex-col gap-3"
                >
                    <div class="space-y-1.5">
                        <Label>{{ trans('Name') }}</Label>
                        <Input v-model="editForm.name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Beschreibung') }}</Label>
                        <Input v-model="editForm.description" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ trans('Status') }}</Label>
                        <select
                            v-model="editForm.status"
                            class="border-input bg-background flex h-9 rounded-md border px-3 py-1 text-sm shadow-xs"
                        >
                            <option
                                v-for="(label, status) in statusLabels"
                                :key="status"
                                :value="status"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <Button @click="saveTemplate(template.id)">{{
                        trans('Speichern')
                    }}</Button>
                </CardContent>
                <CardContent v-else-if="template.description">
                    <p class="text-muted-foreground text-sm">
                        {{ template.description }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
