<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck, LogOut, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="mb-6">
            <h2 class="text-xl font-bold text-white tracking-tight">Verifikasi Email Anda</h2>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                Tautan verifikasi telah dikirimkan ke alamat email Anda. Silakan periksa kotak masuk atau spam email Anda untuk mengaktifkan akses.
            </p>
        </div>

        <div
            class="mb-4 p-3 rounded-lg bg-emerald-950/60 border border-emerald-800 text-xs font-medium text-emerald-300"
            v-if="verificationLinkSent"
        >
            Link verifikasi baru telah berhasil dikirim ulang ke alamat email Anda.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="pt-2 flex flex-col gap-3">
                <Button
                    type="submit"
                    class="w-full h-10 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium shadow-md shadow-sky-950/50 transition-all flex items-center justify-center gap-2"
                    :disabled="form.processing"
                >
                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                    <MailCheck v-else class="h-4 w-4" />
                    <span>Kirim Ulang Email Verifikasi</span>
                </Button>

                <div class="text-center">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="inline-flex items-center justify-center gap-1.5 text-xs text-slate-400 hover:text-rose-400 font-medium py-1 transition-colors"
                    >
                        <LogOut class="h-3.5 w-3.5" />
                        <span>Keluar Sistem</span>
                    </Link>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
