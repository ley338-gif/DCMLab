<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { trans } from '@/lib/trans';

// siehe Register.vue: defineOptions({layout}) laeuft zu frueh fuer trans().
watchEffect(() => {
    setLayoutProps({
        title: trans('Confirm password'),
        description: trans(
            'This is a secure area of the application. Please confirm your password before continuing.',
        ),
    });
});
</script>

<template>
    <Head :title="trans('Confirm password')" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="trans('Confirm with passkey')"
        :loading-label="trans('Confirming...')"
        :separator="trans('Or confirm with password')"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{ trans('Password') }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ trans('Confirm password') }}
                </Button>
            </div>
        </div>
    </Form>
</template>
