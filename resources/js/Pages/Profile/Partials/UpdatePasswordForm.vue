<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { KeyRound, Lock, Check, Loader2 } from 'lucide-vue-next';

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <form @submit.prevent="updatePassword" class="space-y-4">
        <div class="space-y-1.5">
            <label for="current_password" class="block text-xs font-semibold text-slate-700">Kata Sandi Saat Ini</label>
            <div class="relative">
                <Input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="pl-9 h-9 text-sm bg-white"
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi lama"
                />
                <KeyRound class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.current_password" class="mt-1" />
        </div>

        <div class="space-y-1.5">
            <label for="password" class="block text-xs font-semibold text-slate-700">Kata Sandi Baru</label>
            <div class="relative">
                <Input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="pl-9 h-9 text-sm bg-white"
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                />
                <Lock class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.password" class="mt-1" />
        </div>

        <div class="space-y-1.5">
            <label for="password_confirmation" class="block text-xs font-semibold text-slate-700">Konfirmasi Kata Sandi Baru</label>
            <div class="relative">
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="pl-9 h-9 text-sm bg-white"
                    autocomplete="new-password"
                    placeholder="Ulangi kata sandi baru"
                />
                <Lock class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.password_confirmation" class="mt-1" />
        </div>

        <div class="flex items-center gap-3 pt-2">
            <Button 
                type="submit" 
                size="sm"
                :disabled="form.processing || !form.current_password || !form.password" 
                class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white px-4 h-9"
            >
                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Perbarui Kata Sandi
            </Button>

            <Transition
                enter-active-class="transition ease-in-out duration-300"
                enter-from-class="opacity-0 translate-x-1"
                leave-active-class="transition ease-in-out duration-300"
                leave-to-class="opacity-0"
            >
                <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                    <Check class="h-3.5 w-3.5" /> Berhasil Diubah
                </span>
            </Transition>
        </div>
    </form>
</template>
