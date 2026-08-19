<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { Head, usePage } from '@inertiajs/vue3';
import { UserCheck, ShieldCheck, Building, ShieldAlert } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
}>();

const user = computed(() => usePage().props.auth.user as any);
</script>

<template>
    <Head title="Profil Pengguna" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header Title -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Pengaturan Profil Pengguna</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Kelola data pribadi, nomor kontak WhatsApp, dan keamanan akun Anda.
                    </p>
                </div>
            </div>

            <!-- Profile Summary Ribbon (Compact) -->
            <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-kominfo-primary/10 flex items-center justify-center text-kominfo-primary border border-kominfo-primary/20 shrink-0">
                        <UserCheck class="h-6 w-6" />
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-900">{{ user.name }}</h2>
                        <p class="text-xs text-slate-500 font-mono">{{ user.email }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                        <ShieldCheck class="h-3.5 w-3.5 text-kominfo-primary" />
                        <span class="capitalize">Role: {{ user.role.replace('_', ' ') }}</span>
                    </div>
                    <div v-if="user.department" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-slate-100 text-slate-700 text-xs font-medium border border-slate-200">
                        <Building class="h-3.5 w-3.5 text-slate-500" />
                        <span>{{ user.department.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Two-Column Form Grid (Space Efficient) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Data Pribadi -->
                <Card class="border-slate-200 shadow-sm bg-white">
                    <CardHeader class="pb-3 border-b border-slate-100">
                        <CardTitle class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <UserCheck class="h-4 w-4 text-kominfo-primary" />
                            Informasi Profil
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500">
                            Perbarui identitas dan nomor kontak WhatsApp aktif untuk menerima update tiket.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </CardContent>
                </Card>

                <!-- Keamanan Kata Sandi -->
                <Card class="border-slate-200 shadow-sm bg-white">
                    <CardHeader class="pb-3 border-b border-slate-100">
                        <CardTitle class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <ShieldAlert class="h-4 w-4 text-amber-500" />
                            Keamanan Akun
                        </CardTitle>
                        <CardDescription class="text-xs text-slate-500">
                            Gunakan kombinasi kata sandi yang kuat dan aman untuk melindungi akun Anda.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
