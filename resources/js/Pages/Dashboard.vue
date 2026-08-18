<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import {
  Ticket,
  CheckCircle2,
  Clock,
  Activity,
  Building2,
  Cable,
  Network,
  Wifi
} from 'lucide-vue-next';

const props = defineProps<{
    stats: Record<string, number>;
    recentTickets: Array<any>;
}>();

const user = computed(() => usePage().props.auth.user as any);
const role = computed(() => user.value?.role);

// Tanggal format
const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Dashboard Monitoring" />

    <AuthenticatedLayout>
        <template #header>
            Dashboard Monitoring
        </template>

        <div class="space-y-6">
            <!-- OPD User Dashboard -->
            <template v-if="role === 'opd_user'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Tiket Aktif</CardTitle>
                            <Activity class="h-4 w-4 text-kominfo-primary" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.active_tickets || 0 }}</div>
                            <p class="text-xs text-slate-500">Gangguan sedang ditangani</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Selesai Menunggu Konfirmasi</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.resolved_tickets || 0 }}</div>
                            <p class="text-xs text-slate-500">Perlu Anda verifikasi</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Seluruh Laporan</CardTitle>
                            <Ticket class="h-4 w-4 text-slate-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.total_tickets || 0 }}</div>
                            <p class="text-xs text-slate-500">Riwayat laporan instansi Anda</p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- Technician Dashboard -->
            <template v-if="role === 'technician'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Antrean Tiket Baru</CardTitle>
                            <Ticket class="h-4 w-4 text-blue-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.open_tickets || 0 }}</div>
                            <p class="text-xs text-slate-500">Menunggu di-claim / assign</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Tugas Saya (In Progress)</CardTitle>
                            <Clock class="h-4 w-4 text-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.my_progress || 0 }}</div>
                            <p class="text-xs text-slate-500">Sedang Anda tangani saat ini</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Selesai Hari Ini</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.resolved_today || 0 }}</div>
                            <p class="text-xs text-slate-500">Perbaikan sukses hari ini</p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- Admin Dashboard -->
            <template v-if="role === 'admin'">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Tiket Aktif</CardTitle>
                            <Activity class="h-4 w-4 text-kominfo-primary" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.total_active || 0 }}</div>
                            <p class="text-xs text-slate-500">Open & In Progress se-Kota</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Total Terselesaikan</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.total_resolved || 0 }}</div>
                            <p class="text-xs text-slate-500">Resolved & Closed</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Instansi Terdaftar</CardTitle>
                            <Building2 class="h-4 w-4 text-slate-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.total_departments || 0 }}</div>
                            <p class="text-xs text-slate-500">OPD Pemerintahan</p>
                        </CardContent>
                    </Card>
                </div>

                <h3 class="text-lg font-medium text-slate-800 mt-8 mb-4">Statistik Gangguan Kategori</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card class="bg-purple-50/50 border-purple-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-purple-800">Fiber Optic</CardTitle>
                            <Cable class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-900">{{ stats.fiber_optic || 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-cyan-50/50 border-cyan-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-cyan-800">Jaringan LAN</CardTitle>
                            <Network class="h-4 w-4 text-cyan-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-cyan-900">{{ stats.lan || 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-sky-50/50 border-sky-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium text-sky-800">Jaringan WiFi</CardTitle>
                            <Wifi class="h-4 w-4 text-sky-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-sky-900">{{ stats.wifi || 0 }}</div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- Recent Tickets List (Common for all roles, just different data) -->
            <div class="mt-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Aktivitas Tiket Terkini</CardTitle>
                        <CardDescription>Daftar 5 tiket laporan terbaru yang relevan dengan peran Anda.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTickets.length === 0" class="text-center py-8 text-slate-500">
                            Belum ada laporan tiket saat ini.
                        </div>
                        <div v-else class="space-y-4">
                            <div v-for="ticket in recentTickets" :key="ticket.id" class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 rounded-lg border border-slate-100 bg-slate-50/50">
                                <div class="space-y-1 mb-2 sm:mb-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-slate-900">{{ ticket.ticket_number }}</span>
                                        <StatusBadge type="ticket" :status="ticket.status" />
                                        <StatusBadge type="priority" :status="ticket.priority" />
                                    </div>
                                    <p class="text-sm font-medium text-slate-800">{{ ticket.title }}</p>
                                    <div class="text-xs text-slate-500 flex flex-wrap items-center gap-2">
                                        <span v-if="role !== 'opd_user'">{{ ticket.department?.name }}</span>
                                        <span v-if="role !== 'opd_user'">•</span>
                                        <span>{{ ticket.category?.name }}</span>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-start sm:items-end text-sm">
                                    <div class="text-slate-500 mb-1">
                                        {{ formatDate(ticket.created_at) }}
                                    </div>
                                    <div class="text-xs">
                                        <span v-if="ticket.assignee" class="text-slate-600">Teknisi: {{ ticket.assignee.name }}</span>
                                        <span v-else class="text-amber-600 font-medium">Belum Ditugaskan</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>
