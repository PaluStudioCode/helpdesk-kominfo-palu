<script setup lang="ts">
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
  Ticket,
  CheckCircle2,
  Clock,
  Activity,
  Building2,
  Cable,
  Network,
  Wifi,
  ShieldCheck,
  RotateCcw,
  Star,
  Users,
  AlertTriangle,
  ArrowRight
} from 'lucide-vue-next';

const props = defineProps<{
    stats: Record<string, any>;
    recentTickets: Array<any>;
}>();

const user = computed(() => usePage().props.auth.user as any);
const role = computed(() => user.value?.role);

// Tanggal format
const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }) + ' WITA';
};
</script>

<template>
    <Head title="Dashboard Monitoring" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header Title -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Dashboard Monitoring</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Ringkasan metrik operasional layanan helpdesk jaringan dan status penanganan terkini.
                    </p>
                </div>
                <div>
                    <Link :href="route('tickets.index')">
                        <Button variant="outline" size="sm" class="text-xs">
                            Lihat Semua Tiket <ArrowRight class="w-3.5 h-3.5 ml-1.5" />
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- ================= OPD USER DASHBOARD ================= -->
            <template v-if="role === 'opd_user'">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card class="border-blue-200 bg-blue-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-blue-900">Laporan Diproses</CardTitle>
                            <Activity class="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-950">{{ stats.in_process || 0 }}</div>
                            <p class="text-xs text-blue-700 mt-1">Sedang diverifikasi atau ditangani tim</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Laporan Selesai</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.closed_tickets || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Perbaikan selesai & ditutup resmi</p>
                        </CardContent>
                    </Card>

                    <Card class="border-rose-200 bg-rose-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-rose-900">Perlu Perbaikan</CardTitle>
                            <AlertTriangle class="h-4 w-4 text-rose-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-rose-950">{{ stats.needs_fix || 0 }}</div>
                            <p class="text-xs text-rose-700 mt-1">Ditolak & dalam batas 72 jam</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-slate-700">Total Seluruh Laporan</CardTitle>
                            <Ticket class="h-4 w-4 text-slate-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-slate-900">{{ stats.total_reports || 0 }}</div>
                            <p class="text-xs text-slate-500 mt-1">Riwayat laporan instansi Anda</p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- ================= TECHNICIAN DASHBOARD ================= -->
            <template v-if="role === 'technician'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Tugas Tim Saya (In Progress)</CardTitle>
                            <Clock class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.my_team_tickets || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Tiket aktif ditugaskan ke Anda / Tim</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-purple-200 bg-purple-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-900">Menunggu Review Admin</CardTitle>
                            <ShieldCheck class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-950">{{ stats.pending_approval || 0 }}</div>
                            <p class="text-xs text-purple-700 mt-1">Pekerjaan lapangan telah diajukan</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Tuntas Bulan Ini</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.resolved_this_month || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Tiket terverifikasi selesai</p>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- ================= ADMIN DASHBOARD ================= -->
            <template v-if="role === 'admin'">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card class="border-blue-200 bg-blue-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-blue-900">Menunggu Verifikasi Masuk</CardTitle>
                            <Activity class="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-950">{{ stats.pending_admin || 0 }}</div>
                            <p class="text-xs text-blue-700 mt-1">Laporan baru butuh verifikasi awal</p>
                        </CardContent>
                    </Card>

                    <Card class="border-purple-200 bg-purple-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-900">Menunggu Review Mutu</CardTitle>
                            <ShieldCheck class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-950">{{ stats.pending_approval || 0 }}</div>
                            <p class="text-xs text-purple-700 mt-1">Hasil perbaikan teknisi siap di-QC</p>
                        </CardContent>
                    </Card>

                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Sedang Dikerjakan</CardTitle>
                            <Clock class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.in_progress || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Tim teknisi aktif di lapangan</p>
                        </CardContent>
                    </Card>

                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Skor CSAT Layanan</CardTitle>
                            <Star class="h-4 w-4 text-amber-500 fill-amber-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950 flex items-center gap-1.5">
                                <span>{{ stats.avg_csat || '0.0' }}</span>
                                <span class="text-xs text-amber-700 font-normal">/ 5.0 Bintang</span>
                            </div>
                            <p class="text-xs text-amber-700 mt-1">Rata-rata penilaian kepuasan OPD</p>
                        </CardContent>
                    </Card>
                </div>

                <h3 class="text-base font-semibold text-slate-800 mt-6 mb-3">Distribusi Infrastruktur Gangguan</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card class="bg-purple-50/50 border-purple-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-800">Fiber Optic</CardTitle>
                            <Cable class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-900">{{ stats.fiber_optic || 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-cyan-50/50 border-cyan-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-cyan-800">Jaringan LAN</CardTitle>
                            <Network class="h-4 w-4 text-cyan-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-cyan-900">{{ stats.lan || 0 }}</div>
                        </CardContent>
                    </Card>
                    <Card class="bg-sky-50/50 border-sky-100">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-sky-800">Jaringan WiFi</CardTitle>
                            <Wifi class="h-4 w-4 text-sky-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-sky-900">{{ stats.wifi || 0 }}</div>
                        </CardContent>
                    </Card>
                </div>
            </template>

            <!-- ================= RECENT TICKETS LIST ================= -->
            <div class="mt-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Aktivitas Tiket Terkini</CardTitle>
                        <CardDescription>Daftar 5 laporan tiket terbaru yang relevan dengan peran Anda.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="recentTickets.length === 0" class="text-center py-8 text-slate-500 text-sm">
                            Belum ada laporan tiket saat ini.
                        </div>
                        <div v-else class="space-y-3">
                            <div 
                                v-for="ticket in recentTickets" 
                                :key="ticket.id" 
                                class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-white hover:border-slate-200 transition-all gap-3"
                            >
                                <div class="space-y-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-mono font-bold text-slate-900 text-xs sm:text-sm">{{ ticket.ticket_number }}</span>
                                        <StatusBadge type="ticket" :status="ticket.status" />
                                        <StatusBadge v-if="ticket.priority" type="priority" :status="ticket.priority" />
                                    </div>
                                    <p class="text-sm font-semibold text-slate-800 truncate max-w-md">{{ ticket.title }}</p>
                                    <div class="text-xs text-slate-500 flex flex-wrap items-center gap-2">
                                        <span v-if="role !== 'opd_user'">{{ ticket.department?.name }}</span>
                                        <span v-if="role !== 'opd_user' && ticket.category">•</span>
                                        <span v-if="ticket.category">{{ ticket.category.name }}</span>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-start sm:items-end text-xs shrink-0 gap-1.5">
                                    <div class="text-slate-400">
                                        {{ formatDate(ticket.created_at) }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Multi-technician display -->
                                        <div v-if="ticket.technicians && ticket.technicians.length > 0" class="flex flex-wrap gap-1">
                                            <span 
                                                v-for="tech in ticket.technicians" 
                                                :key="tech.id"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-800 border border-emerald-200"
                                            >
                                                {{ tech.name }}
                                            </span>
                                        </div>
                                        <span v-else-if="ticket.assignee" class="text-slate-700 font-medium">
                                            {{ ticket.assignee.name }}
                                        </span>
                                        <span v-else class="text-slate-400 italic">
                                            Belum Ditugaskan
                                        </span>

                                        <Link :href="route('tickets.show', ticket.id)">
                                            <Button variant="outline" size="sm" class="h-7 text-xs px-2.5 ml-2">
                                                Detail
                                            </Button>
                                        </Link>
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
