<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { trans } from '@/lib/trans';

// siehe Register.vue: defineOptions({layout}) laeuft zu frueh fuer trans().
watchEffect(() => {
    setLayoutProps({
        title: trans('Email verification'),
        description: trans(
            'Please verify your email address by clicking on the link we just emailed to you.',
        ),
    });
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head :title="trans('Email verification')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{
            trans(
                'A new verification link has been sent to the email address you provided during registration.',
            )
        }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ trans('Resend verification email') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ trans('Log out') }}
        </TextLink>
    </Form>
</template>
