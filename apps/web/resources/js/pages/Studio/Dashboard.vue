<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { index as authorPanelIndex } from '@/routes/author';
import { index as studioLabsIndex } from '@/routes/studio/labs';
import { index as studioNodesIndex } from '@/routes/studio/nodes';
import { index as sandboxTemplatesIndex } from '@/routes/studio/sandbox-templates';
import { index as studioTracksIndex } from '@/routes/studio/tracks';

const props = defineProps<{
    role: 'learner' | 'author' | 'reviewer' | 'administrator';
    can_manage_sandbox_templates: boolean;
    sandbox_template_count: number;
    can_manage_tracks: boolean;
    track_count: number;
    can_manage_nodes: boolean;
    node_count: number;
    can_manage_labs: boolean;
    lab_count: number;
}>();

const roleLabels: Record<string, string> = {
    author: trans('Autor:in'),
    reviewer: trans('Reviewer:in'),
    administrator: trans('Administrator:in'),
};
</script>

<template>
    <Head :title="trans('Studio')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[{ title: trans('Studio'), href: '' }]"
        />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">
                    {{ trans('DCMLab Studio') }}
                </h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{
                        trans(
                            'Wächst schrittweise neben dem Autoren-Panel — bisherige Editoren bleiben erreichbar.',
                        )
                    }}
                </p>
            </div>
            <Badge variant="outline">{{ roleLabels[props.role] }}</Badge>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Tracks') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(':count Tracks insgesamt.', {
                                count: props.track_count,
                            })
                        }}
                    </p>
                    <Link :href="studioTracksIndex()">
                        <Button variant="outline">{{
                            props.can_manage_tracks
                                ? trans('Tracks verwalten')
                                : trans('Tracks ansehen')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Nodes') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(':count Nodes insgesamt.', {
                                count: props.node_count,
                            })
                        }}
                    </p>
                    <Link :href="studioNodesIndex()">
                        <Button variant="outline">{{
                            props.can_manage_nodes
                                ? trans('Nodes verwalten')
                                : trans('Nodes ansehen')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Labs') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(':count Labs insgesamt.', {
                                count: props.lab_count,
                            })
                        }}
                    </p>
                    <Link :href="studioLabsIndex()">
                        <Button variant="outline">{{
                            props.can_manage_labs
                                ? trans('Labs verwalten')
                                : trans('Labs ansehen')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Sandbox-Vorlagen') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(':count Vorlagen im Katalog.', {
                                count: props.sandbox_template_count,
                            })
                        }}
                    </p>
                    <Link :href="sandboxTemplatesIndex()">
                        <Button variant="outline">{{
                            props.can_manage_sandbox_templates
                                ? trans('Vorlagen verwalten')
                                : trans('Vorlagen ansehen')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ trans('Autoren-Panel') }}</CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <p class="text-muted-foreground text-sm">
                        {{
                            trans(
                                'Lektions-, Prüfungs-, Quiz- und Achievement-Editoren sind vorerst weiterhin dort.',
                            )
                        }}
                    </p>
                    <Link :href="authorPanelIndex()">
                        <Button variant="outline">{{
                            trans('Zum Autoren-Panel')
                        }}</Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
