<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowLeft, Loader2, Send } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Pemulihan Kata Sandi" />

        <div class="mb-6">
            <h2 class="text-xl font-bold text-white tracking-tight">Lupa Kata Sandi</h2>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                Masukkan alamat email dinas Anda yang terdaftar. Kami akan mengirimkan tautan reset kata sandi melalui email.
            </p>
        </div>

        <div
            v-if="status"
            class="mb-4 p-3 rounded-lg bg-emerald-950/60 border border-emerald-800 text-xs font-medium text-emerald-300"
        >
            {{ status }}
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
                        placeholder="nama@palukota.go.id"
                    />
                    <Mail class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                </div>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div class="pt-2 flex flex-col gap-3">
                <Button
                    type="submit"
                    class="w-full h-10 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium shadow-md shadow-sky-950/50 transition-all flex items-center justify-center gap-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <Send v-else class="h-4 w-4" />
                    <span>Kirim Tautan Reset</span>
                </Button>

                <Link
                    :href="route('login')"
                    class="inline-flex items-center justify-center gap-1.5 text-xs text-slate-400 hover:text-white font-medium py-1 transition-colors"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Kembali ke Halaman Masuk</span>
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
