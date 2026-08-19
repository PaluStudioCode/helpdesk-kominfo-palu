<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, ShieldCheck, Loader2 } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Kata Sandi" />

        <div class="mb-6">
            <h2 class="text-xl font-bold text-white tracking-tight">Konfirmasi Keamanan</h2>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                Ini adalah area sensitif dan terproteksi. Silakan masukkan kata sandi akun Anda untuk melanjutkan.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <label for="password" class="block text-xs font-semibold text-slate-300">
                    Kata Sandi
                </label>
                <div class="relative">
                    <Input
                        id="password"
                        type="password"
                        class="pl-9 h-10 text-sm bg-slate-950/80 border-slate-700 text-white placeholder:text-slate-500 focus:border-kominfo-primary focus:ring-kominfo-primary"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        autofocus
                        placeholder="••••••••"
                    />
                    <Lock class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div class="pt-2">
                <Button
                    type="submit"
                    class="w-full h-10 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium shadow-md shadow-sky-950/50 transition-all flex items-center justify-center gap-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <ShieldCheck v-else class="h-4 w-4" />
                    <span>Konfirmasi & Lanjutkan</span>
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
