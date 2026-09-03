<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import DataTable from '@/Components/DataTable.vue';
import { FileSpreadsheet, Printer, RotateCcw, Loader2, Eye, FileText, Shield, Clock, ExternalLink } from 'lucide-vue-next';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface Technician {
    id: number;
    name: string;
}

interface Ticket {
    id: number;
    ticket_number: string;
    department: { name: string };
    category: { name: string } | null;
    reporter: { name: string };
    assignee: { name: string } | null;
    technicians?: { id: number; name: string }[];
    network_type: string | null;
    title: string;
    description?: string;
    location_details?: string;
    resolution_note?: string;
    priority: string;
    status: string;
    created_at: string;
    due_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
}

const props = defineProps<{
    tickets: {
        data: Ticket[];
        links: any[];
        total: number;
        from: number;
        to: number;
        current_page: number;
        last_page: number;
        per_page: number;
    };
    departments: Department[];
    technicians: Technician[];
    filters: {
        start_date?: string;
        end_date?: string;
        department_id?: string;
        network_type?: string;
        status?: string;
        assigned_to?: string;
    };
}>();

const form = reactive({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    department_id: props.filters.department_id || 'all',
    network_type: props.filters.network_type || 'all',
    status: props.filters.status || 'all',
});

const applyFilters = () => {
    router.get(route('admin.reports.index'), form, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleSelectChange = (key: keyof typeof form, val: string) => {
    form[key] = val;
    applyFilters();
};

const resetFilters = () => {
    form.start_date = '';
    form.end_date = '';
    form.department_id = 'all';
    form.network_type = 'all';
    form.status = 'all';
    applyFilters();
};

const handlePage = (page: number) => {
    router.get(route('admin.reports.index'), {
        ...form,
        page
    }, { preserveState: true });
};

// Modal Detail state
const selectedTicket = ref<Ticket | null>(null);
const isDetailModalOpen = ref(false);

const openDetailModal = (ticket: Ticket) => {
    selectedTicket.value = ticket;
    isDetailModalOpen.value = true;
};

// Export states
const isExportingPdf = ref(false);
const isExportingExcel = ref(false);

const downloadPdf = async () => {
    if (isExportingPdf.value) return;
    isExportingPdf.value = true;
    try {
        const response = await axios.get(route('admin.reports.export.pdf'), {
            params: form,
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `Laporan-Rekapitulasi-Helpdesk-${new Date().toISOString().slice(0, 10)}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Gagal mengekspor PDF:', error);
    } finally {
        isExportingPdf.value = false;
    }
};

const downloadExcel = async () => {
    if (isExportingExcel.value) return;
    isExportingExcel.value = true;
    try {
        const response = await axios.get(route('admin.reports.export.excel'), {
            params: form,
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `Laporan-Rekapitulasi-Helpdesk-${new Date().toISOString().slice(0, 10)}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Gagal mengekspor Excel:', error);
    } finally {
        isExportingExcel.value = false;
    }
};

// Exact 6-Column Standard: No Tiket, Instansi & Kendala, Kategori, Status, Kinerja, Aksi
const tableColumns = [
    { key: 'ticket_number', label: 'No. Tiket' },
    { key: 'ticket_info', label: 'Instansi & Kendala' },
    { key: 'technical_spec', label: 'Kategori' },
    { key: 'status', label: 'Status' },
    { key: 'performance', label: 'Kinerja' },
    { key: 'actions', label: 'Aksi' },
];

// Helper functions for clean colored text & Indonesian labels
const getStatusLabel = (status: string): string => {
    const map: Record<string, string> = {
        pending_admin: 'Menunggu Verifikasi',
        in_progress: 'Sedang Dikerjakan',
        pending_approval: 'Menunggu Review Admin',
        closed: 'Selesai',
        cancelled: 'Ditolak',
    };
    return map[status] || status;
};

const getStatusColor = (status: string): string => {
    const map: Record<string, string> = {
        pending_admin: 'text-blue-600',
        in_progress: 'text-amber-600',
        pending_approval: 'text-purple-600',
        closed: 'text-emerald-600',
        cancelled: 'text-rose-600',
    };
    return map[status] || 'text-slate-600';
};

const getPriorityLabel = (priority: string): string => {
    const map: Record<string, string> = {
        emergency: 'Darurat',
        high: 'Tinggi',
        medium: 'Sedang',
        low: 'Rendah',
    };
    return map[priority] || priority;
};

const getPriorityColor = (priority: string): string => {
    const map: Record<string, string> = {
        emergency: 'text-rose-600 font-bold',
        high: 'text-amber-600 font-semibold',
        medium: 'text-blue-600 font-medium',
        low: 'text-slate-500 font-normal',
    };
    return map[priority] || 'text-slate-600';
};

const getNetworkLabel = (network: string | null): string => {
    if (!network) return '-';
    const map: Record<string, string> = {
        fiber_optic: 'Fiber Optic',
        lan: 'Jaringan LAN',
        wifi: 'WiFi Nirkabel',
    };
    return map[network] || network;
};

const getNetworkColor = (network: string | null): string => {
    if (!network) return 'text-slate-400';
    const map: Record<string, string> = {
        fiber_optic: 'text-cyan-700',
        lan: 'text-indigo-700',
        wifi: 'text-violet-700',
    };
    return map[network] || 'text-slate-700';
};

const getSlaReportStatus = (ticket: Ticket): { label: string; color: string } => {
    if (ticket.status === 'cancelled') {
        return { label: 'Dibatalkan', color: 'text-slate-400' };
    }

    if (!ticket.due_at) {
        return { label: '-', color: 'text-slate-400' };
    }

    const dueAt = new Date(ticket.due_at).getTime();
    const completionTime = ticket.resolved_at 
        ? new Date(ticket.resolved_at).getTime() 
        : (ticket.closed_at ? new Date(ticket.closed_at).getTime() : null);

    if (['resolved', 'closed'].includes(ticket.status) && completionTime) {
        if (completionTime <= dueAt) {
            return { label: 'Tepat Waktu', color: 'text-emerald-600' };
        } else {
            return { label: 'Terlambat', color: 'text-rose-600' };
        }
    }

    const now = new Date().getTime();
    const diffHours = (dueAt - now) / (1000 * 60 * 60);

    if (diffHours < 0) {
        return { label: 'Overdue SLA', color: 'text-rose-600 font-bold' };
    } else if (diffHours <= 2) {
        return { label: 'Mendekati Batas', color: 'text-amber-600 font-semibold' };
    } else {
        return { label: 'Dalam Target', color: 'text-emerald-600' };
    }
};

const getHandlingDuration = (ticket: Ticket): string => {
    if (!ticket.created_at || ticket.status === 'cancelled') return '-';

    const start = new Date(ticket.created_at).getTime();
    let end: number;

    if (['resolved', 'closed'].includes(ticket.status)) {
        if (ticket.resolved_at) {
            end = new Date(ticket.resolved_at).getTime();
        } else if (ticket.closed_at) {
            end = new Date(ticket.closed_at).getTime();
        } else {
            return '-';
        }
    } else {
        end = new Date().getTime();
    }

    const diffMinutes = Math.max(0, Math.floor((end - start) / (1000 * 60)));
    const days = Math.floor(diffMinutes / (60 * 24));
    const hours = Math.floor((diffMinutes % (60 * 24)) / 60);
    const minutes = diffMinutes % 60;

    const parts = [];
    if (days > 0) parts.push(`${days}h`);
    if (hours > 0) parts.push(`${hours}j`);
    if (minutes > 0 || parts.length === 0) parts.push(`${minutes}m`);

    const formattedDuration = parts.join(' ');

    if (!['resolved', 'closed'].includes(ticket.status)) {
        return `${formattedDuration} (berjalan)`;
    }

    return formattedDuration;
};

const formatDateTime = (dateStr: string | null) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Laporan & Rekapitulasi" />

    <AuthenticatedLayout>
        <template #header>
            Laporan & Rekapitulasi
        </template>

        <div class="space-y-6">
            <!-- Header Title and Export Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Laporan & Rekapitulasi Gangguan</h1>
                </div>
                <div class="flex items-center gap-2.5 shrink-0">
                    <Button 
                        variant="outline" 
                        class="h-9 px-3.5 gap-2 text-rose-700 border-rose-200 hover:bg-rose-50 text-xs sm:text-sm font-medium shadow-2xs" 
                        :disabled="isExportingPdf || isExportingExcel"
                        @click="downloadPdf"
                    >
                        <Loader2 v-if="isExportingPdf" class="h-4 w-4 animate-spin" />
                        <Printer v-else class="h-4 w-4 text-rose-600" />
                        <span>{{ isExportingPdf ? 'Mengekspor PDF...' : 'Ekspor PDF' }}</span>
                    </Button>
                    <Button 
                        class="h-9 px-3.5 gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-medium shadow-2xs" 
                        :disabled="isExportingPdf || isExportingExcel"
                        @click="downloadExcel"
                    >
                        <Loader2 v-if="isExportingExcel" class="h-4 w-4 animate-spin" />
                        <FileSpreadsheet v-else class="h-4 w-4" />
                        <span>{{ isExportingExcel ? 'Mengekspor Excel...' : 'Ekspor Excel' }}</span>
                    </Button>
                </div>
            </div>

            <!-- Unified DataTable with Integrated Filter Bar -->
            <div class="space-y-4">
                <!-- Compact Inline Filter Toolbar -->
                <div class="p-3.5 bg-white border border-slate-200 rounded-xl shadow-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5">
                        <!-- Date Start -->
                        <div>
                            <Input 
                                type="date" 
                                v-model="form.start_date" 
                                @change="applyFilters"
                                class="h-9 text-xs" 
                                title="Tanggal Mulai"
                            />
                        </div>

                        <!-- Date End -->
                        <div>
                            <Input 
                                type="date" 
                                v-model="form.end_date" 
                                @change="applyFilters"
                                class="h-9 text-xs" 
                                title="Tanggal Selesai"
                            />
                        </div>

                        <!-- Department Filter -->
                        <div>
                            <Select :modelValue="form.department_id" @update:modelValue="(v) => handleSelectChange('department_id', v)">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Instansi" />
                                </SelectTrigger>
                                <SelectContent class="max-h-56">
                                    <SelectItem value="all">Semua Instansi / OPD</SelectItem>
                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Network Type Filter -->
                        <div>
                            <Select :modelValue="form.network_type" @update:modelValue="(v) => handleSelectChange('network_type', v)">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Jaringan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Jaringan</SelectItem>
                                    <SelectItem value="fiber_optic">Fiber Optic (FO)</SelectItem>
                                    <SelectItem value="lan">Jaringan LAN</SelectItem>
                                    <SelectItem value="wifi">WiFi / Nirkabel</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Status Filter & Reset Button -->
                        <div class="flex items-center gap-2">
                            <Select :modelValue="form.status" @update:modelValue="(v) => handleSelectChange('status', v)" class="flex-1">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="pending_admin">Menunggu Verifikasi</SelectItem>
                                    <SelectItem value="in_progress">Sedang Dikerjakan</SelectItem>
                                    <SelectItem value="pending_approval">Menunggu Review Admin</SelectItem>
                                    <SelectItem value="closed">Selesai</SelectItem>
                                    <SelectItem value="cancelled">Ditolak</SelectItem>
                                </SelectContent>
                            </Select>

                            <Button 
                                variant="outline" 
                                size="icon" 
                                class="h-9 w-9 shrink-0 text-slate-500 hover:text-slate-900 border-slate-200 cursor-pointer" 
                                @click="resetFilters" 
                                title="Reset Filter"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- DataTable Component (Spacious 6 Columns) -->
                <DataTable 
                    :columns="tableColumns" 
                    :data="tickets"
                    @page="handlePage"
                >
                    <!-- Column 1: Ticket Number -->
                    <template #cell-ticket_number="{ item }">
                        <button 
                            type="button" 
                            @click="openDetailModal(item)"
                            class="font-mono font-medium text-blue-600 hover:text-blue-800 hover:underline text-xs sm:text-sm transition-colors text-left cursor-pointer"
                        >
                            {{ item.ticket_number }}
                        </button>
                    </template>

                    <!-- Column 2: Department & Title -->
                    <template #cell-ticket_info="{ item }">
                        <div class="space-y-0.5 max-w-sm">
                            <p class="font-semibold text-slate-900 text-xs truncate" :title="item.department?.name">{{ item.department?.name || '-' }}</p>
                            <p class="text-xs text-slate-500 truncate" :title="item.title">{{ item.title }}</p>
                        </div>
                    </template>

                    <!-- Column 3: Category & Network -->
                    <template #cell-technical_spec="{ item }">
                        <div class="space-y-0.5 max-w-xs">
                            <p class="font-semibold text-xs" :class="getNetworkColor(item.network_type)">
                                {{ getNetworkLabel(item.network_type) }}
                            </p>
                            <p class="text-[11px] text-slate-500 truncate" :title="item.category?.name">
                                {{ item.category?.name || '-' }}
                            </p>
                        </div>
                    </template>

                    <!-- Column 4: Status -->
                    <template #cell-status="{ item }">
                        <span class="text-xs font-semibold" :class="getStatusColor(item.status)">
                            {{ getStatusLabel(item.status) }}
                        </span>
                    </template>

                    <!-- Column 5: Performance (Handling Duration & SLA Status) -->
                    <template #cell-performance="{ item }">
                        <div class="space-y-0.5">
                            <span class="text-xs font-mono font-bold text-slate-800">
                                {{ getHandlingDuration(item) }}
                            </span>
                            <p class="text-[11px] font-semibold" :class="getSlaReportStatus(item).color">
                                {{ getSlaReportStatus(item).label }}
                            </p>
                        </div>
                    </template>

                    <!-- Column 6: Action Button -->
                    <template #cell-actions="{ item }">
                        <Button 
                            type="button"
                            variant="outline" 
                            size="sm" 
                            class="h-7 text-xs px-2.5 border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 font-medium cursor-pointer"
                            @click="openDetailModal(item)"
                        >
                            <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" /> Detail
                        </Button>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- ================= MODAL DETAIL REKAPITULASI TIKET ================= -->
        <Dialog v-model:open="isDetailModalOpen">
            <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
                <DialogHeader class="border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <DialogTitle class="text-base font-bold font-mono text-slate-900">
                            {{ selectedTicket?.ticket_number }}
                        </DialogTitle>
                        <span class="text-slate-300 font-light">•</span>
                        <span v-if="selectedTicket" class="text-xs font-semibold" :class="getStatusColor(selectedTicket.status)">
                            {{ getStatusLabel(selectedTicket.status) }}
                        </span>
                    </div>
                </DialogHeader>

                <div v-if="selectedTicket" class="py-3 space-y-6">
                    <!-- 1. Data Pengaduan (OPD) -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 pb-1 border-b border-slate-100 flex items-center gap-1.5">
                            <FileText class="w-3.5 h-3.5 text-slate-400" /> Informasi Pengaduan (OPD)
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-lg bg-slate-50/70 border border-slate-200/80 mb-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Instansi (OPD)</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ selectedTicket.department?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Pelapor</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ selectedTicket.reporter?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lokasi / Ruangan</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ (selectedTicket as any).location_details || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Pengajuan</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ formatDateTime(selectedTicket.created_at) }}</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-700">Judul Masalah:</p>
                            <p class="text-sm font-medium text-slate-900 bg-white p-2.5 rounded-lg border border-slate-200">
                                {{ selectedTicket.title }}
                            </p>
                        </div>
                        <div v-if="(selectedTicket as any).description" class="space-y-1 mt-2.5">
                            <p class="text-xs font-semibold text-slate-700">Deskripsi Kendala:</p>
                            <div class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap bg-white p-3 rounded-lg border border-slate-200">
                                {{ (selectedTicket as any).description }}
                            </div>
                        </div>
                    </div>

                    <!-- 2. Parameter Teknis (Diskominfo) -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 pb-1 border-b border-slate-100 flex items-center gap-1.5">
                            <Shield class="w-3.5 h-3.5 text-slate-400" /> Parameter Penanganan Teknis
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 p-3.5 rounded-lg bg-slate-50/70 border border-slate-200/80">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Infrastruktur</p>
                                <p class="font-bold text-xs sm:text-sm mt-0.5" :class="getNetworkColor(selectedTicket.network_type)">
                                    {{ getNetworkLabel(selectedTicket.network_type) }}
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ selectedTicket.category?.name || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Prioritas</p>
                                <p class="text-sm mt-0.5 font-bold" :class="getPriorityColor(selectedTicket.priority)">
                                    {{ getPriorityLabel(selectedTicket.priority) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Target SLA</p>
                                <p class="font-bold text-slate-900 text-xs sm:text-sm mt-0.5">{{ selectedTicket.due_at ? formatDateTime(selectedTicket.due_at) : '-' }}</p>
                                <p class="text-xs mt-0.5 font-semibold" :class="getSlaReportStatus(selectedTicket).color">
                                    {{ getSlaReportStatus(selectedTicket).label }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tim Teknisi</p>
                                <div v-if="selectedTicket.technicians && selectedTicket.technicians.length > 0" class="flex flex-wrap gap-1 mt-1">
                                    <span 
                                        v-for="tech in selectedTicket.technicians" 
                                        :key="tech.id"
                                        class="text-xs font-semibold text-slate-800 bg-white border border-slate-200 px-1.5 py-0.5 rounded"
                                    >
                                        {{ tech.name }}
                                    </span>
                                </div>
                                <span v-else-if="selectedTicket.assignee" class="text-xs font-bold text-slate-900 mt-0.5 block">
                                    {{ selectedTicket.assignee.name }}
                                </span>
                                <span v-else class="text-xs text-slate-400 italic mt-0.5 block">Belum ditugaskan</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Durasi & Hasil Penanganan -->
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 pb-1 border-b border-slate-100 flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5 text-slate-400" /> Durasi & Hasil Penanganan
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3.5 rounded-lg bg-slate-50/70 border border-slate-200/80 mb-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Total Durasi Penanganan</p>
                                <p class="font-bold font-mono text-slate-900 text-sm mt-0.5">{{ getHandlingDuration(selectedTicket) }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Selesai</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">
                                    {{ (selectedTicket.resolved_at || selectedTicket.closed_at) ? formatDateTime(selectedTicket.resolved_at || selectedTicket.closed_at) : '-' }}
                                </p>
                            </div>
                        </div>

                        <div v-if="(selectedTicket as any).resolution_note" class="space-y-1">
                            <p class="text-xs font-semibold text-slate-700">Tindakan Solusi Lapangan:</p>
                            <div class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap bg-white p-3 rounded-lg border border-slate-200">
                                {{ (selectedTicket as any).resolution_note }}
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-slate-100 pt-3 flex items-center justify-between sm:justify-between w-full">
                    <Button 
                        type="button" 
                        variant="outline" 
                        class="text-xs border-slate-200 text-slate-700 cursor-pointer"
                        @click="isDetailModalOpen = false"
                    >
                        Tutup
                    </Button>

                    <Link 
                        v-if="selectedTicket"
                        :href="route('tickets.show', selectedTicket.id)" 
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs font-semibold transition-colors shadow-2xs"
                    >
                        <span>Buka Lembar Tiket</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </Link>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
