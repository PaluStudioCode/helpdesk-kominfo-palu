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
import { FileSpreadsheet, Printer, RotateCcw, Loader2, Eye, ExternalLink } from 'lucide-vue-next';
import { getHandlingDuration } from '@/lib/ticket-helpers';

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
    department?: { id: number; name: string; code?: string } | null;
    category?: { id: number; name: string; infrastructure_type?: string } | null;
    reporter?: { id: number; name: string; phone_number?: string | null } | null;
    assignee?: { id: number; name: string; role?: string } | null;
    technicians?: { id: number; name: string; phone_number?: string | null }[];
    infrastructure_type?: string | null;
    network_type?: string | null;
    title: string;
    description?: string | null;
    location_details?: string | null;
    resolution_note?: string | null;
    affected_device?: string | null;
    actual_repair_location?: string | null;
    inspection_result?: string | null;
    root_cause?: string | null;
    action_taken?: string | null;
    materials_used?: string | null;
    test_result?: string | null;
    test_parameters?: string | null;
    priority: string;
    status: string;
    created_at: string;
    due_at: string | null;
    assigned_at?: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    cancelled_at?: string | null;
    hold_reason_category?: string | null;
    hold_reason_note?: string | null;
    hold_started_at?: string | null;
    total_hold_duration_minutes?: number;
    rating?: number | null;
    feedback_comment?: string | null;
    rated_at?: string | null;
    resolution?: {
        id?: number;
        category_id?: number | null;
        affected_device?: string | null;
        actual_repair_location?: string | null;
        inspection_result?: string | null;
        root_cause?: string | null;
        action_taken?: string | null;
        materials_used?: string | null;
        test_result?: string | null;
        test_parameters?: string | null;
        resolution_note?: string | null;
        resolved_by?: number | null;
        created_at?: string;
        category?: { id: number; name: string; infrastructure_type?: string } | null;
        resolver?: { id: number; name: string; role?: string } | null;
    } | null;
    feedback?: {
        rating?: number;
        feedback_comment?: string | null;
        rater?: { name: string } | null;
        created_at?: string;
    } | null;
    latest_hold?: {
        reason_category?: string;
        reason_note?: string;
        user?: { name: string };
    } | null;
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
        search?: string;
        start_date?: string;
        end_date?: string;
        department_id?: string;
        infrastructure_type?: string;
        network_type?: string;
        status?: string;
        assigned_to?: string;
    };
}>();

const form = reactive({
    search: props.filters.search || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    department_id: props.filters.department_id || 'all',
    infrastructure_type: props.filters.infrastructure_type || props.filters.network_type || 'all',
    network_type: props.filters.infrastructure_type || props.filters.network_type || 'all',
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
    form.search = '';
    form.start_date = '';
    form.end_date = '';
    form.department_id = 'all';
    form.infrastructure_type = 'all';
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
        on_hold: 'Tertunda (On-Hold)',
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
        on_hold: 'text-amber-700',
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
        'Fiber optic': 'Fiber optic',
        'Perangkat/Akses': 'Perangkat/Akses',
        'Power/poe': 'Power/poe',
        'Converter': 'Converter',
        'Layanan/jaringan': 'Layanan/jaringan',
        fiber_optic: 'Fiber optic',
        lan: 'Perangkat/Akses',
        wifi: 'Layanan/jaringan',
    };
    return map[network] || network;
};

const getNetworkColor = (network: string | null): string => {
    if (!network) return 'text-slate-400';
    const map: Record<string, string> = {
        'Fiber optic': 'text-sky-700',
        'Perangkat/Akses': 'text-indigo-700',
        'Power/poe': 'text-amber-700',
        'Converter': 'text-pink-700',
        'Layanan/jaringan': 'text-emerald-700',
        fiber_optic: 'text-sky-700',
        lan: 'text-indigo-700',
        wifi: 'text-emerald-700',
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
        : (ticket.resolution?.created_at 
            ? new Date(ticket.resolution.created_at).getTime() 
            : (ticket.closed_at ? new Date(ticket.closed_at).getTime() : null));

    if (['resolved', 'closed', 'pending_approval'].includes(ticket.status) && completionTime) {
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

                        <!-- Infrastructure Type Filter -->
                        <div>
                            <Select :modelValue="form.infrastructure_type" @update:modelValue="(v) => { form.infrastructure_type = v; form.network_type = v; applyFilters(); }">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Infrastruktur" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Infrastruktur</SelectItem>
                                    <SelectItem value="Fiber optic">Fiber optic</SelectItem>
                                    <SelectItem value="Perangkat/Akses">Perangkat/Akses</SelectItem>
                                    <SelectItem value="Power/poe">Power/poe</SelectItem>
                                    <SelectItem value="Converter">Converter</SelectItem>
                                    <SelectItem value="Layanan/jaringan">Layanan/jaringan</SelectItem>
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
                                    <SelectItem value="on_hold">Tertunda (On-Hold)</SelectItem>
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
                    v-model="form.search"
                    @update:modelValue="applyFilters"
                    @page="handlePage"
                    searchPlaceholder="Cari no. tiket, instansi, kendala..."
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

                    <!-- Column 3: Category & Infrastructure -->
                    <template #cell-technical_spec="{ item }">
                        <div class="space-y-0.5 max-w-xs">
                            <p class="font-semibold text-xs" :class="getNetworkColor(item.resolution?.category?.infrastructure_type || item.infrastructure_type || item.network_type)">
                                {{ getNetworkLabel(item.resolution?.category?.infrastructure_type || item.infrastructure_type || item.network_type) }}
                            </p>
                            <p class="text-[11px] text-slate-500 truncate" :title="item.resolution?.category?.name || item.category?.name">
                                {{ item.resolution?.category?.name || item.category?.name || '-' }}
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

        <!-- ================= MODAL DETAIL RINGKASAN REKAPITULASI TIKET ================= -->
        <Dialog v-model:open="isDetailModalOpen">
            <DialogContent class="sm:max-w-[560px] max-h-[90vh] overflow-y-auto p-4 sm:p-5">
                <!-- Header Ringkas & Padat -->
                <DialogHeader class="border-b border-slate-200 pb-2.5">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <div>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block">Ringkasan Rekapitulasi</span>
                            <DialogTitle class="text-base font-bold font-mono text-slate-900 mt-0.5 flex items-center gap-2 flex-wrap">
                                <span>{{ selectedTicket?.ticket_number }}</span>
                                <span class="text-slate-300 font-normal hidden sm:inline">•</span>
                                <span class="text-xs font-semibold" :class="getStatusColor(selectedTicket?.status || '')">
                                    {{ getStatusLabel(selectedTicket?.status || '') }}
                                </span>
                                <span class="text-slate-300 font-normal hidden sm:inline">•</span>
                                <span class="text-xs font-medium" :class="getPriorityColor(selectedTicket?.priority || '')">
                                    Prioritas: {{ getPriorityLabel(selectedTicket?.priority || '') }}
                                </span>
                            </DialogTitle>
                        </div>
                    </div>
                </DialogHeader>

                <div v-if="selectedTicket" class="py-3 space-y-3 text-xs text-slate-800">
                    <!-- Grid Parameter Rekapitulasi Inti (2 Kolom Bergaris Bersih) -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden bg-white divide-y divide-slate-200">
                        <!-- Baris 1: Instansi & Pelapor -->
                        <div class="grid grid-cols-2 divide-x divide-slate-200">
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Instansi / OPD</span>
                                <span class="text-xs font-semibold text-slate-900 block mt-0.5 truncate" :title="selectedTicket.department?.name">
                                    {{ selectedTicket.department?.name || '-' }}
                                </span>
                            </div>
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Pelapor</span>
                                <span class="text-xs font-medium text-slate-900 block mt-0.5 truncate">
                                    {{ selectedTicket.reporter?.name || '-' }}
                                    <span v-if="selectedTicket.reporter?.phone_number" class="text-slate-500 font-normal">({{ selectedTicket.reporter.phone_number }})</span>
                                </span>
                            </div>
                        </div>

                        <!-- Baris 2: Kategori Masalah & Infrastruktur -->
                        <div class="grid grid-cols-2 divide-x divide-slate-200">
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Infrastruktur</span>
                                <span class="text-xs font-bold block mt-0.5" :class="getNetworkColor(selectedTicket.resolution?.category?.infrastructure_type || selectedTicket.infrastructure_type || selectedTicket.network_type)">
                                    {{ getNetworkLabel(selectedTicket.resolution?.category?.infrastructure_type || selectedTicket.infrastructure_type || selectedTicket.network_type) }}
                                </span>
                            </div>
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Kategori Masalah</span>
                                <span class="text-xs font-medium text-slate-900 block mt-0.5 truncate" :title="selectedTicket.resolution?.category?.name || selectedTicket.category?.name">
                                    {{ selectedTicket.resolution?.category?.name || selectedTicket.category?.name || '-' }}
                                </span>
                            </div>
                        </div>

                        <!-- Baris 3: Petugas Teknisi & Status Kinerja SLA -->
                        <div class="grid grid-cols-2 divide-x divide-slate-200">
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Petugas / Tim Teknisi</span>
                                <span class="text-xs font-medium text-slate-900 block mt-0.5 truncate">
                                    <template v-if="selectedTicket.technicians && selectedTicket.technicians.length > 0">
                                        {{ selectedTicket.technicians.map(t => t.name).join(', ') }}
                                    </template>
                                    <template v-else-if="selectedTicket.assignee">
                                        {{ selectedTicket.assignee.name }}
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400 italic">Belum ditugaskan</span>
                                    </template>
                                </span>
                            </div>
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Kinerja SLA</span>
                                <span class="text-xs font-semibold block mt-0.5" :class="getSlaReportStatus(selectedTicket).color">
                                    {{ getSlaReportStatus(selectedTicket).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Baris 4: Waktu Lapor & Waktu Selesai -->
                        <div class="grid grid-cols-2 divide-x divide-slate-200">
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Waktu Lapor</span>
                                <span class="text-xs font-medium text-slate-900 block mt-0.5">{{ formatDateTime(selectedTicket.created_at) }}</span>
                            </div>
                            <div class="p-2.5">
                                <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Waktu Selesai</span>
                                <span class="text-xs font-medium text-slate-900 block mt-0.5">
                                    {{ (selectedTicket.resolved_at || selectedTicket.resolution?.created_at || selectedTicket.closed_at) 
                                        ? formatDateTime(selectedTicket.resolved_at || selectedTicket.resolution?.created_at || selectedTicket.closed_at) 
                                        : '-' }}
                                </span>
                            </div>
                        </div>

                        <!-- Baris 5: Total Waktu Penanganan (Durasi) -->
                        <div class="p-2.5 bg-slate-50/50">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Total Waktu Penanganan</span>
                            <span class="text-xs font-bold font-mono text-slate-900 block mt-0.5">{{ getHandlingDuration(selectedTicket) }}</span>
                        </div>
                    </div>

                    <!-- Uraian Singkat: Kendala & Tindakan Penanganan Inti -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden bg-white divide-y divide-slate-100">
                        <div class="p-2.5">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Masalah yang Dilaporkan</span>
                            <p class="text-xs font-medium text-slate-900 mt-0.5 leading-snug">{{ selectedTicket.title }}</p>
                        </div>
                        <div class="p-2.5">
                            <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider block">Tindakan Solusi Penanganan</span>
                            <p class="text-xs text-slate-800 mt-0.5 leading-relaxed whitespace-pre-wrap font-medium">
                                {{ selectedTicket.resolution?.action_taken || selectedTicket.action_taken || selectedTicket.resolution_note || (selectedTicket.status === 'in_progress' ? 'Sedang dalam pengerjaan teknisi.' : 'Belum ada tindakan penanganan.') }}
                            </p>
                        </div>
                        <div v-if="selectedTicket.feedback || selectedTicket.rating" class="p-2.5 bg-amber-50/40">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-semibold text-amber-800 uppercase tracking-wider">Evaluasi Kepuasan (CSAT)</span>
                                <span class="text-xs font-bold text-amber-700">⭐ {{ selectedTicket.feedback?.rating || selectedTicket.rating }} / 5</span>
                            </div>
                            <p v-if="selectedTicket.feedback?.feedback_comment || selectedTicket.feedback_comment" class="text-xs text-slate-700 mt-1 italic">
                                "{{ selectedTicket.feedback?.feedback_comment || selectedTicket.feedback_comment }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer Aksi -->
                <DialogFooter class="border-t border-slate-200 pt-3 flex items-center justify-between sm:justify-between w-full">
                    <Button 
                        type="button" 
                        variant="outline" 
                        class="h-8 px-3 text-xs border-slate-200 text-slate-700 hover:bg-slate-50 cursor-pointer"
                        @click="isDetailModalOpen = false"
                    >
                        Tutup
                    </Button>

                    <Link 
                        v-if="selectedTicket"
                        :href="route('tickets.show', selectedTicket.id)" 
                        class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs font-semibold transition-colors shadow-2xs"
                    >
                        <span>Lihat Tiket Lengkap</span>
                        <ExternalLink class="w-3.5 h-3.5" />
                    </Link>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
