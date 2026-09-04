<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import FileUpload from '@/Components/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Plus, Eye } from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

import CreateTicketModal from '@/Components/CreateTicketModal.vue';

const props = defineProps<{
    tickets: any;
    filters: any;
}>();

const currentUser = computed(() => usePage().props.auth.user as any);
const canCreateTicket = computed(() => currentUser.value?.role === 'opd_user');

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const infrastructureFilter = ref(props.filters?.infrastructure_type || props.filters?.network_type || 'all');
const networkFilter = infrastructureFilter;

// Create Ticket Modal State (OPD Complaint)
const isCreateModalOpen = ref(false);

const openCreateTicketModal = () => {
    isCreateModalOpen.value = true;
};

const handleSearch = (value: string) => {
    router.get(route('tickets.index'), {
        ...props.filters,
        search: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleStatusFilter = (value: string) => {
    statusFilter.value = value;
    router.get(route('tickets.index'), {
        ...props.filters,
        status: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleInfrastructureFilter = (value: string) => {
    infrastructureFilter.value = value;
    router.get(route('tickets.index'), {
        ...props.filters,
        infrastructure_type: value,
        network_type: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};
const handleNetworkFilter = handleInfrastructureFilter;

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('tickets.index'), {
        ...props.filters,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('tickets.index'), {
        ...props.filters,
        page
    }, { preserveState: true });
};

const columns = computed(() => {
    if (currentUser.value?.role === 'technician') {
        return [
            { key: 'ticket_number', label: 'No. Tiket', sortable: true },
            { key: 'department', label: 'Instansi (OPD)', sortable: false },
            { key: 'title', label: 'Kendala Teknis', sortable: false },
            { key: 'priority', label: 'Prioritas', sortable: true },
            { key: 'sla', label: 'Target SLA', sortable: false },
            { key: 'status', label: 'Status', sortable: true },
        ];
    }
    return [
        { key: 'ticket_number', label: 'No. Tiket', sortable: true },
        { key: 'title', label: 'Judul Masalah', sortable: false },
        { key: 'department', label: 'Instansi (OPD)', sortable: false },
        { key: 'priority', label: 'Prioritas', sortable: true },
        { key: 'status', label: 'Status', sortable: true },
    ];
});

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'pending_admin': return 'Menunggu Verifikasi';
        case 'in_progress': return 'Sedang Dikerjakan';
        case 'on_hold': return 'Tertunda (On-Hold)';
        case 'pending_approval': return 'Menunggu Review';
        case 'closed': return 'Selesai';
        case 'cancelled': return 'Ditolak';
        default: return status || '-';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'pending_admin': return 'text-blue-600 font-semibold';
        case 'in_progress': return 'text-amber-600 font-semibold';
        case 'on_hold': return 'text-amber-700 font-semibold';
        case 'pending_approval': return 'text-purple-600 font-semibold';
        case 'closed': return 'text-emerald-600 font-semibold';
        case 'cancelled': return 'text-rose-600 font-semibold';
        default: return 'text-slate-600 font-medium';
    }
};

const getPriorityLabel = (priority: string) => {
    switch (priority) {
        case 'emergency': return 'Darurat';
        case 'high': return 'Tinggi';
        case 'medium': return 'Sedang';
        case 'low': return 'Rendah';
        default: return priority || '-';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'emergency': return 'text-rose-600 font-semibold';
        case 'high': return 'text-amber-600 font-semibold';
        case 'medium': return 'text-blue-600 font-semibold';
        case 'low': return 'text-slate-500 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric', month: 'short', year: 'numeric'
    });
};
</script>

<template>
    <Head :title="currentUser?.role === 'technician' ? 'Daftar Penugasan Tiket' : 'Antrean Tiket'" />

    <AuthenticatedLayout>
        <template #header>
            {{ currentUser?.role === 'technician' ? 'Daftar Penugasan Tiket' : 'Antrean Tiket Gangguan' }}
        </template>

        <div class="space-y-6">
            <!-- Header Title and Description -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                        {{ currentUser?.role === 'technician' ? 'Daftar Penugasan Tiket Lapangan' : 'Antrean & Riwayat Tiket Gangguan' }}
                    </h1>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ currentUser?.role === 'technician' 
                            ? 'Daftar seluruh tiket perbaikan jaringan yang ditugaskan kepada Anda, baik sebagai penanggung jawab utama maupun tim pendukung.'
                            : 'Pantau status penanganan, kelola verifikasi dan eskalasi kendala jaringan, serta buat laporan baru.' 
                        }}
                    </p>
                </div>
            </div>

            <DataTable 
                :columns="columns" 
                :data="tickets"
                :modelValue="searchQuery"
                @update:modelValue="handleSearch"
                @sort="handleSort"
                @page="handlePage"
                searchPlaceholder="Cari no tiket atau judul..."
            >
                <!-- Filter Dropdown Options -->
                <template #filters>
                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full sm:w-auto">
                        <Select :modelValue="statusFilter" @update:modelValue="handleStatusFilter">
                            <SelectTrigger class="w-full sm:w-[170px] bg-white text-xs h-9">
                                <SelectValue placeholder="Status Tiket" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ currentUser?.role === 'technician' ? 'Semua Tugas Saya' : 'Semua Status' }}</SelectItem>
                                <SelectItem v-if="currentUser?.role !== 'technician'" value="pending_admin">Menunggu Verifikasi</SelectItem>
                                <SelectItem value="in_progress">Sedang Dikerjakan</SelectItem>
                                <SelectItem value="on_hold">Tertunda (On-Hold)</SelectItem>
                                <SelectItem value="pending_approval">Menunggu Review Admin</SelectItem>
                                <SelectItem value="closed">Selesai</SelectItem>
                                <SelectItem value="cancelled">Ditolak</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :modelValue="infrastructureFilter" @update:modelValue="handleInfrastructureFilter">
                            <SelectTrigger class="w-full sm:w-[140px] bg-white text-xs h-9">
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
                </template>

                <template #actions>
                    <Button v-if="canCreateTicket" @click="openCreateTicketModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark w-full sm:w-auto text-xs sm:text-sm h-9">
                        <Plus class="w-4 h-4 mr-1.5" /> Buat Laporan Baru
                    </Button>
                </template>

                <template #cell-ticket_number="{ item }">
                    <Link :href="route('tickets.show', item.id)" class="group block">
                        <div class="font-medium text-blue-600 font-mono text-xs sm:text-sm group-hover:underline">
                            {{ item.ticket_number }}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5 capitalize">
                            {{ (item.infrastructure_type || item.network_type) ? (item.infrastructure_type || item.network_type).replace('_', ' ') : '-' }}
                        </div>
                    </Link>
                </template>

                <template #cell-title="{ item }">
                    <Link :href="route('tickets.show', item.id)" class="block group">
                        <span class="font-medium text-slate-900 text-xs sm:text-sm group-hover:text-blue-600 transition-colors line-clamp-1" :title="item.title">
                            {{ item.title }}
                        </span>
                    </Link>
                </template>

                <template #cell-department="{ item }">
                    <span class="text-xs sm:text-sm text-slate-700 font-medium block truncate max-w-[200px]" :title="item.department ? item.department.name : '-'">
                        {{ item.department ? item.department.name : '-' }}
                    </span>
                </template>

                <template #cell-priority="{ item }">
                    <span :class="getPriorityColor(item.priority)" class="text-xs sm:text-sm whitespace-nowrap">
                        {{ getPriorityLabel(item.priority) }}
                    </span>
                </template>

                <template #cell-sla="{ item }">
                    <span 
                        class="text-xs sm:text-sm whitespace-nowrap"
                        :class="item.is_overdue ? 'text-rose-600 font-medium' : 'text-slate-600'"
                    >
                        {{ item.due_human || '-' }}
                    </span>
                </template>

                <template #cell-status="{ item }">
                    <span :class="getStatusColor(item.status)" class="text-xs sm:text-sm whitespace-nowrap">
                        {{ getStatusLabel(item.status) }}
                    </span>
                </template>

                <template #actions-cell="{ item }">
                    <Link :href="route('tickets.show', item.id)">
                        <Button variant="outline" size="sm" class="h-7 text-xs px-2.5 border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 font-medium">
                            <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" /> {{ currentUser?.role === 'technician' ? 'Buka' : 'Detail' }}
                        </Button>
                    </Link>
                </template>
            </DataTable>
        </div>

        <!-- Create Ticket Modal Dialog (OPD Quick Form) -->
        <CreateTicketModal v-model:open="isCreateModalOpen" />
    </AuthenticatedLayout>
</template>

