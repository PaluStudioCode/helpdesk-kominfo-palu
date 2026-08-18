<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import InputLabel from '@/Components/InputLabel.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import StatusBadge from '@/Components/ui/status-badge/StatusBadge.vue';
import DataTable from '@/Components/DataTable.vue';
import { FileSpreadsheet, Printer, Filter, RotateCcw, Download } from 'lucide-vue-next';

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

const resetFilters = () => {
    form.start_date = '';
    form.end_date = '';
    form.department_id = 'all';
    form.network_type = 'all';
    form.status = 'all';
    form.assigned_to = 'all';
    applyFilters();
};

const downloadPdf = () => {
    const query = new URLSearchParams(form as any).toString();
    window.open(route('admin.reports.export.pdf') + '?' + query, '_blank');
};

const downloadExcel = () => {
    const query = new URLSearchParams(form as any).toString();
    window.location.href = route('admin.reports.export.excel') + '?' + query;
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
    <Head title="Laporan & Rekapitulasi - Helpdesk Kominfo Palu" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Laporan & Rekapitulasi</h2>
                    <p class="text-sm text-slate-500">Filter, evaluasi, dan cetak rekapitulasi gangguan jaringan kota Palu</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" class="gap-2 text-rose-700 border-rose-200 hover:bg-rose-50" @click="downloadPdf">
                        <Printer class="h-4 w-4" />
                        Ekspor PDF
                    </Button>
                    <Button class="gap-2 bg-emerald-600 hover:bg-emerald-700 text-white" @click="downloadExcel">
                        <FileSpreadsheet class="h-4 w-4" />
                        Ekspor Excel
                    </Button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Filter Card -->
            <Card>
                <CardHeader class="pb-3 border-b border-slate-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Filter class="h-5 w-5 text-kominfo-primary" />
                            <CardTitle class="text-base font-semibold">Parameter Filter Laporan</CardTitle>
                        </div>
                        <Button variant="ghost" size="sm" class="text-slate-500 h-8 gap-1.5" @click="resetFilters">
                            <RotateCcw class="h-3.5 w-3.5" />
                            Reset Filter
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="pt-4">
                    <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <!-- Tanggal Mulai -->
                        <div class="space-y-1.5">
                            <InputLabel for="start_date" value="Tanggal Mulai" class="text-xs text-slate-600 font-medium" />
                            <Input id="start_date" type="date" v-model="form.start_date" class="h-9 text-xs" />
                        </div>

                        <!-- Tanggal Selesai -->
                        <div class="space-y-1.5">
                            <InputLabel for="end_date" value="Tanggal Selesai" class="text-xs text-slate-600 font-medium" />
                            <Input id="end_date" type="date" v-model="form.end_date" class="h-9 text-xs" />
                        </div>

                        <!-- Filter OPD -->
                        <div class="space-y-1.5">
                            <InputLabel value="Instansi / OPD" class="text-xs text-slate-600 font-medium" />
                            <Select v-model="form.department_id">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua OPD" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Instansi / OPD</SelectItem>
                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="String(dept.id)">
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Filter Jenis Jaringan -->
                        <div class="space-y-1.5">
                            <InputLabel value="Jenis Jaringan" class="text-xs text-slate-600 font-medium" />
                            <Select v-model="form.network_type">
                                <SelectTrigger class="h-9 text-xs">
                                    <SelectValue placeholder="Semua Jaringan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Jaringan</SelectItem>
                                    <SelectItem value="fiber_optic">Fiber Optic (FO)</SelectItem>
                                    <SelectItem value="lan">Jaringan LAN</SelectItem>
                                    <SelectItem value="wifi">WiFi / Hotspot</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Filter Status -->
                        <div class="space-y-1.5">
                            <InputLabel value="Status Tiket" class="text-xs text-slate-600 font-medium" />
                            <Select v-model="form.status">
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

                        <!-- Filter Teknisi & Aksi -->
                        <div class="space-y-1.5 flex flex-col justify-end">
                            <InputLabel value="Teknisi" class="text-xs text-slate-600 font-medium" />
                            <Select v-model="form.assigned_to">
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
                        </div>

                        <div class="md:col-span-3 lg:col-span-6 flex justify-end">
                            <Button type="submit" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs h-9 px-4">
                                Terapkan Filter
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Table Preview -->
            <Card>
                <CardHeader class="pb-3 border-b border-slate-100 flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-base font-semibold">Pratinjau Data Rekapitulasi</CardTitle>
                        <CardDescription class="text-xs">Menampilkan {{ tickets.total }} tiket sesuai kriteria filter</CardDescription>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <DataTable :columns="tableColumns" :data="tickets.data" :pagination="tickets">
                        <template #cell-ticket_number="{ row }">
                            <span class="font-mono font-medium text-kominfo-primary text-xs">{{ row.ticket_number }}</span>
                        </template>

                        <template #cell-department="{ row }">
                            <span class="font-medium text-slate-800 text-xs">{{ row.department?.name || '-' }}</span>
                        </template>

                        <template #cell-network_type="{ row }">
                            <StatusBadge type="network" :value="row.network_type" />
                        </template>

                        <template #cell-title="{ row }">
                            <span class="text-xs font-medium text-slate-900 block truncate max-w-xs">{{ row.title }}</span>
                        </template>

                        <template #cell-priority="{ row }">
                            <StatusBadge type="priority" :value="row.priority" />
                        </template>

                        <template #cell-status="{ row }">
                            <StatusBadge type="ticket" :value="row.status" />
                        </template>

                        <template #cell-assignee="{ row }">
                            <span class="text-xs text-slate-600">{{ row.assignee?.name || '-' }}</span>
                        </template>

                        <template #cell-created_at="{ row }">
                            <span class="text-xs text-slate-500">{{ new Date(row.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
