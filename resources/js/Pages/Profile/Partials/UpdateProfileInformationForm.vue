<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, Phone, Check, Loader2 } from 'lucide-vue-next';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = usePage().props.auth.user as any;

const form = useForm({
    name: user.name,
    email: user.email,
    phone_number: user.phone_number || '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-1.5">
            <label for="name" class="block text-xs font-semibold text-slate-700">Nama Lengkap</label>
            <div class="relative">
                <Input
                    id="name"
                    type="text"
                    class="pl-9 h-9 text-sm bg-white"
                    v-model="form.name"
                    required
                    autocomplete="name"
                    placeholder="Nama lengkap pengguna"
                />
                <User class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.name" class="mt-1" />
        </div>

        <div class="space-y-1.5">
            <label for="email" class="block text-xs font-semibold text-slate-700">Alamat Email</label>
            <div class="relative">
                <Input
                    id="email"
                    type="email"
                    class="pl-9 h-9 text-sm bg-white"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="nama@palukota.go.id"
                />
                <Mail class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.email" class="mt-1" />
        </div>
        
        <div class="space-y-1.5">
            <div class="flex items-center justify-between">
                <label for="phone_number" class="block text-xs font-semibold text-slate-700">Nomor WhatsApp</label>
                <span class="text-[11px] text-slate-400">Notifikasi Tiket</span>
            </div>
            <div class="relative">
                <Input
                    id="phone_number"
                    type="text"
                    class="pl-9 h-9 text-sm bg-white"
                    v-model="form.phone_number"
                    required
                    placeholder="08123456789 atau 628..."
                />
                <Phone class="absolute left-3 top-2.5 h-4 w-4 text-slate-400 pointer-events-none" />
            </div>
            <InputError :message="form.errors.phone_number" class="mt-1" />
        </div>

        <div v-if="mustVerifyEmail && user.email_verified_at === null" class="p-3 rounded-md bg-amber-50 border border-amber-200 text-xs text-amber-800">
            <span>Email Anda belum diverifikasi. </span>
            <Link
                :href="route('verification.send')"
                method="post"
                as="button"
                class="font-semibold underline hover:text-amber-900 focus:outline-none"
            >
                Kirim ulang email verifikasi.
            </Link>
            <div v-show="status === 'verification-link-sent'" class="mt-1 font-medium text-emerald-600">
                Link verifikasi baru telah dikirim ke email Anda.
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <Button 
                type="submit" 
                size="sm"
                :disabled="form.processing || !form.isDirty" 
                class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white px-4 h-9"
            >
                <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                Simpan Perubahan
            </Button>

            <Transition
                enter-active-class="transition ease-in-out duration-300"
                enter-from-class="opacity-0 translate-x-1"
                leave-active-class="transition ease-in-out duration-300"
                leave-to-class="opacity-0"
            >
                <span v-if="form.recentlySuccessful" class="inline-flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                    <Check class="h-3.5 w-3.5" /> Tersimpan
                </span>
            </Transition>
        </div>
    </form>
</template>
