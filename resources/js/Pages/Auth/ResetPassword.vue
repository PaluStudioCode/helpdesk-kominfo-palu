<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Lock, Loader2, KeyRound } from 'lucide-vue-next';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Atur Ulang Kata Sandi" />

        <div class="mb-6">
            <h2 class="text-xl font-bold text-white tracking-tight">Atur Ulang Kata Sandi</h2>
            <p class="text-xs text-slate-400 mt-1">
                Silakan buat kata sandi baru yang aman untuk akun Anda.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <label for="email" class="block text-xs font-semibold text-slate-300">
                    Alamat Email
                </label>
                <div class="relative">
                    <Input
                        id="email"
                        type="email"
                        class="pl-9 h-10 text-sm bg-slate-950/80 border-slate-700 text-white placeholder:text-slate-500 focus:border-kominfo-primary focus:ring-kominfo-primary"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                    />
                    <Mail class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div class="space-y-1.5">
                <label for="password" class="block text-xs font-semibold text-slate-300">
                    Kata Sandi Baru
                </label>
                <div class="relative">
                    <Input
                        id="password"
                        type="password"
                        class="pl-9 h-10 text-sm bg-slate-950/80 border-slate-700 text-white placeholder:text-slate-500 focus:border-kominfo-primary focus:ring-kominfo-primary"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                    />
                    <Lock class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <div class="space-y-1.5">
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-300">
                    Konfirmasi Kata Sandi Baru
                </label>
                <div class="relative">
                    <Input
                        id="password_confirmation"
                        type="password"
                        class="pl-9 h-10 text-sm bg-slate-950/80 border-slate-700 text-white placeholder:text-slate-500 focus:border-kominfo-primary focus:ring-kominfo-primary"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi kata sandi baru"
                    />
                    <Lock class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                </div>
                <InputError class="mt-1" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <Button
                    type="submit"
                    class="w-full h-10 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium shadow-md shadow-sky-950/50 transition-all flex items-center justify-center gap-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <KeyRound v-else class="h-4 w-4" />
                    <span>Simpan Kata Sandi Baru</span>
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
