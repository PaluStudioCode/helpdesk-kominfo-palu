<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, Lock, Eye, EyeOff, Loader2, LogIn } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk ke Sistem Helpdesk" />

        <div v-if="status" class="mb-4 p-3 rounded-lg bg-emerald-950/60 border border-emerald-800 text-xs font-medium text-emerald-300">
            {{ status }}
        </div>

        <div class="mb-6">
            <h2 class="text-xl font-bold text-white tracking-tight">Masuk ke Sistem</h2>
            <p class="text-xs text-slate-400 mt-1">
                Silakan masukkan email dan kata sandi akun resmi Anda.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email Input -->
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

            <!-- Password Input -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-xs font-semibold text-slate-300">
                        Kata Sandi
                    </label>
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-[11px] text-sky-400 hover:text-sky-300 font-medium hover:underline focus:outline-none"
                    >
                        Lupa kata sandi?
                    </Link>
                </div>
                <div class="relative">
                    <Input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="pl-9 pr-10 h-10 text-sm bg-slate-950/80 border-slate-700 text-white placeholder:text-slate-500 focus:border-kominfo-primary focus:ring-kominfo-primary"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <Lock class="absolute left-3 top-3 h-4 w-4 text-slate-400 pointer-events-none" />
                    <button 
                        type="button" 
                        @click="showPassword = !showPassword"
                        class="absolute right-3 top-3 text-slate-400 hover:text-slate-200 focus:outline-none"
                        title="Tampilkan / Sembunyikan Kata Sandi"
                    >
                        <EyeOff v-if="showPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
                <InputError class="mt-1" :message="form.errors.password" />
            </div>

            <!-- Remember Me Checkbox -->
            <div class="pt-1 flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <Checkbox name="remember" v-model:checked="form.remember" class="border-slate-700 bg-slate-950" />
                    <span class="text-xs text-slate-300">Ingat perangkat ini</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <Button
                    type="submit"
                    class="w-full h-10 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium shadow-md shadow-sky-950/50 transition-all flex items-center justify-center gap-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <LogIn v-else class="h-4 w-4" />
                    <span>Masuk ke Layanan</span>
                </Button>
            </div>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-800 text-center">
            <p class="text-[11px] text-slate-400">
                Kendala akun atau akses OPD? Hubungi <span class="font-semibold text-slate-300">Administrator Diskominfo</span>
            </p>
        </div>
    </GuestLayout>
</template>
