<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import PublicProfileController from '@/actions/App/Http/Controllers/Settings/PublicProfileController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/public-profile';
import { show as showProfile } from '@/routes/profiles';
import { trans } from '@/lib/trans';

const props = defineProps<{
    publicSlug: string;
    leaderboardOptIn: boolean;
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: trans('Public profile settings'),
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="trans('Public profile settings')" />

    <h1 class="sr-only">{{ trans('Public profile settings') }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="trans('Public profile')"
            :description="
                trans('Control whether your profile appears on the leaderboard')
            "
        />

        <div class="grid gap-2">
            <Label>{{ trans('Your public profile link') }}</Label>
            <a
                :href="showProfile(props.publicSlug).url"
                target="_blank"
                rel="noopener"
                class="text-foreground text-sm underline decoration-neutral-300 underline-offset-4 hover:decoration-current"
            >
                {{ showProfile(props.publicSlug).url }}
            </a>
        </div>

        <Form
            v-bind="PublicProfileController.update.form()"
            class="space-y-6"
            v-slot="{ processing }"
        >
            <div class="flex items-center gap-2">
                <Checkbox
                    id="leaderboard_opt_in"
                    name="leaderboard_opt_in"
                    :default-checked="props.leaderboardOptIn"
                />
                <Label for="leaderboard_opt_in">{{
                    trans('Show me on the leaderboard')
                }}</Label>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-public-profile-button"
                    >{{ trans('Save') }}</Button
                >
            </div>
        </Form>
    </div>
</template>
