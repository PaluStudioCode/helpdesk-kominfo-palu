<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import StatusBadge from '@/Components/ui/status-badge/StatusBadge.vue';
import DataTable from '@/Components/DataTable.vue';
import { FileSpreadsheet, Printer, RotateCcw, Loader2 } from 'lucide-vue-next';

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
    category: { name: string };
    reporter: { name: string };
    assignee: { name: string } | null;
    network_type: string;
    title: string;
    priority: string;
    status: string;
    created_at: string;
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
    assigned_to: props.filters.assigned_to || 'all',
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
    form.assigned_to = 'all';
    applyFilters();
};

const handlePage = (page: number) => {
    router.get(route('admin.reports.index'), {
        ...form,
        page
    }, { preserveState: true });
};

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
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `Laporan-Rekapitulasi-Helpdesk-${new Date().toISOString().slice(0, 10)}.csv`);
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

const tableColumns = [
    { key: 'ticket_number', label: 'No. Tiket' },
    { key: 'department', label: 'OPD / Instansi' },
    { key: 'network_type', label: 'Jaringan' },
    { key: 'title', label: 'Judul Laporan' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'status', label: 'Status' },
    { key: 'assignee', label: 'Teknisi' },
    { key: 'created_at', label: 'Waktu Dibuat' },
];
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
                    <p class="text-sm text-slate-500 mt-1">
                        Filter, analisis, dan ekspor data rekapitulasi gangguan jaringan ke format PDF maupun Excel.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <Button 
                        variant="outline" 
                        class="gap-2 text-rose-700 border-rose-200 hover:bg-rose-50 shadow-sm" 
                        :disabled="isExportingPdf || isExportingExcel"
                        @click="downloadPdf"
                    >
                        <Loader2 v-if="isExportingPdf" class="h-4 w-4 animate-spin" />
                        <Printer v-else class="h-4 w-4" />
                        {{ isExportingPdf ? 'Mengekspor PDF...' : 'Ekspor PDF' }}
                    </Button>
                    <Button 
                        class="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm" 
                        :disabled="isExportingPdf || isExportingExcel"
                        @click="downloadExcel"
                    >
                        <Loader2 v-if="isExportingExcel" class="h-4 w-4 animate-spin" />
                        <FileSpreadsheet v-else class="h-4 w-4" />
                        {{ isExportingExcel ? 'Mengekspor Excel...' : 'Ekspor Excel' }}
                    </Button>
                </div>
            </div>

            <!-- Unified DataTable with Integrated Filter Bar -->
            <div class="space-y-4">
                <!-- Compact Inline Filter Toolbar -->
                <div class="p-3 bg-white border border-slate-200 rounded-lg shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5">
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
                                    <SelectValue placeholder="Semua OPD" />
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

                        <!-- Status Filter -->
                        <div>
                            <Select :modelValue="form.status" @update:modelValue="(v) => handleSelectChange('status', v)">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Status</SelectItem>
                                    <SelectItem value="open">Open (Baru)</SelectItem>
                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                    <SelectItem value="resolved">Resolved (Selesai)</SelectItem>
                                    <SelectItem value="closed">Closed (Ditutup)</SelectItem>
                                    <SelectItem value="cancelled">Cancelled</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Technician Filter & Reset Button -->
                        <div class="flex items-center gap-2">
                            <Select :modelValue="form.assigned_to" @update:modelValue="(v) => handleSelectChange('assigned_to', v)" class="flex-1">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Teknisi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Teknisi</SelectItem>
                                    <SelectItem v-for="tech in technicians" :key="tech.id" :value="String(tech.id)">
                                        {{ tech.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Button 
                                variant="outline" 
                                size="icon" 
                                class="h-9 w-9 shrink-0 text-slate-500 hover:text-slate-900 border-slate-200" 
                                @click="resetFilters" 
                                title="Reset Filter"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- DataTable Component -->
                <DataTable 
                    :columns="tableColumns" 
                    :data="tickets"
                    @page="handlePage"
                >
                    <template #cell-ticket_number="{ item }">
                        <span class="font-mono font-medium text-kominfo-primary text-xs">{{ item.ticket_number }}</span>
                    </template>

                    <template #cell-department="{ item }">
                        <span class="font-medium text-slate-800 text-xs">{{ item.department?.name || '-' }}</span>
                    </template>

                    <template #cell-network_type="{ item }">
                        <StatusBadge type="network" :status="item.network_type" />
                    </template>

                    <template #cell-title="{ item }">
                        <span class="text-xs font-medium text-slate-900 block truncate max-w-xs" :title="item.title">{{ item.title }}</span>
                    </template>

                    <template #cell-priority="{ item }">
                        <StatusBadge type="priority" :status="item.priority" />
                    </template>

                    <template #cell-status="{ item }">
                        <StatusBadge type="ticket" :status="item.status" />
                    </template>

                    <template #cell-assignee="{ item }">
                        <span class="text-xs text-slate-600">{{ item.assignee?.name || '-' }}</span>
                    </template>

                    <template #cell-created_at="{ item }">
                        <span class="text-xs text-slate-500">{{ new Date(item.created_at).toLocaleDateString('id-ID', { timeZone: 'Asia/Makassar', day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                    </template>
                </DataTable>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
