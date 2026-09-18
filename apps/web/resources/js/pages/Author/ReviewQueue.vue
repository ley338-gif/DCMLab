<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import PageContainer from '@/components/PageContainer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { trans } from '@/lib/trans';
import { index as tracksIndex } from '@/routes/tracks';
import { index as authorIndex } from '@/routes/author';
import { publish } from '@/routes/author/quiz-versions';

type QueueItem = {
    version_id: number;
    activity_type: string;
    activity_key: string;
    title: string;
    author_name: string | null;
    edit_url: string | null;
};

const props = defineProps<{
    items: QueueItem[];
}>();

const publishing = ref<number | null>(null);

function publishVersion(versionId: number) {
    publishing.value = versionId;
    router.post(
        publish.url({ version: versionId }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                publishing.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="trans('Freigabe-Warteschlange')" />

    <PageContainer>
        <Breadcrumbs
            class="mb-6"
            :breadcrumbs="[
                { title: trans('Tracks'), href: tracksIndex() },
                { title: trans('Autoren-Panel'), href: authorIndex() },
                { title: trans('Freigabe-Warteschlange'), href: '' },
            ]"
        />

        <h1 class="mb-6 text-2xl font-semibold">
            {{ trans('Freigabe-Warteschlange') }}
        </h1>

        <p
            v-if="props.items.length === 0"
            class="text-muted-foreground text-sm"
        >
            {{ trans('Keine Einreichungen warten aktuell auf Freigabe.') }}
        </p>

        <div v-else class="flex flex-col gap-3">
            <Card v-for="item in props.items" :key="item.version_id">
                <CardContent
                    class="flex items-center justify-between gap-4 pt-6"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ item.title }}</span>
                            <Badge variant="secondary">{{
                                item.activity_type
                            }}</Badge>
                        </div>
                        <p
                            v-if="item.author_name"
                            class="text-muted-foreground text-sm"
                        >
                            {{
                                trans('Eingereicht von :name', {
                                    name: item.author_name,
                                })
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="item.edit_url" :href="item.edit_url">
                            <Button variant="outline">{{
                                trans('Ansehen')
                            }}</Button>
                        </Link>
                        <Button
                            :disabled="publishing === item.version_id"
                            @click="publishVersion(item.version_id)"
                        >
                            {{ trans('Freigeben') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageContainer>
</template>
