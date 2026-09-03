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
import { Plus, Eye, Cable, Network, Wifi } from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
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

const props = defineProps<{
    tickets: any;
    filters: any;
    canCreateOnBehalf: boolean;
    categoriesMap?: Record<string, Array<{id: number, name: string, network_type: string}>>;
    departments?: Array<{id: number, name: string}>;
    technicians?: Array<{id: number, name: string, phone_number?: string}>;
}>();

const currentUser = computed(() => usePage().props.auth.user as any);
const canCreateTicket = computed(() => ['admin', 'opd_user'].includes(currentUser.value?.role));

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const networkFilter = ref(props.filters?.network_type || 'all');

// Create Ticket Modal State & Form
const isCreateModalOpen = ref(false);
const form = useForm({
    title: '',
    location_details: '',
    description: '',
    attachments: [] as File[],
    // Admin On-Behalf Fields
    department_id: '',
    network_type: '',
    category_id: '',
    priority: 'medium',
    technician_ids: [] as number[],
});

const availableCategories = computed(() => {
    if (!form.network_type || !props.categoriesMap) return [];
    return props.categoriesMap[form.network_type] || [];
});

const handleNetworkChange = (type: string) => {
    form.network_type = type;
    form.category_id = '';
};

const toggleTechnician = (techId: number) => {
    const index = form.technician_ids.indexOf(techId);
    if (index === -1) {
        form.technician_ids.push(techId);
    } else {
        form.technician_ids.splice(index, 1);
    }
};

const openCreateTicketModal = () => {
    form.reset();
    form.clearErrors();
    form.title = '';
    form.location_details = '';
    form.description = '';
    form.network_type = '';
    form.category_id = '';
    form.priority = 'medium';
    form.department_id = '';
    form.technician_ids = [];
    form.attachments = [];
    isCreateModalOpen.value = true;
};

const submitCreateTicket = () => {
    form.post(route('tickets.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
        }
    });
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

const handleNetworkFilter = (value: string) => {
    networkFilter.value = value;
    router.get(route('tickets.index'), {
        ...props.filters,
        network_type: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};

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
                                <SelectItem value="pending_approval">Menunggu Review Admin</SelectItem>
                                <SelectItem value="closed">Selesai</SelectItem>
                                <SelectItem value="cancelled">Ditolak</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :modelValue="networkFilter" @update:modelValue="handleNetworkFilter">
                            <SelectTrigger class="w-full sm:w-[140px] bg-white text-xs h-9">
                                <SelectValue placeholder="Tipe Jaringan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Jaringan</SelectItem>
                                <SelectItem value="fiber_optic">Fiber Optic</SelectItem>
                                <SelectItem value="lan">Jaringan LAN</SelectItem>
                                <SelectItem value="wifi">WiFi / Nirkabel</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </template>

                <template #actions>
                    <Button v-if="canCreateTicket" @click="openCreateTicketModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark w-full sm:w-auto text-xs sm:text-sm h-9">
                        <Plus class="w-4 h-4 mr-1.5" /> {{ canCreateOnBehalf ? 'Buat Tiket (On-Behalf)' : 'Buat Laporan Baru' }}
                    </Button>
                </template>

                <template #cell-ticket_number="{ item }">
                    <Link :href="route('tickets.show', item.id)" class="group block">
                        <div class="font-medium text-blue-600 font-mono text-xs sm:text-sm group-hover:underline">
                            {{ item.ticket_number }}
                        </div>
                        <div class="text-[11px] text-slate-400 mt-0.5 capitalize">
                            {{ item.network_type ? item.network_type.replace('_', ' ') : '-' }}
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

        <!-- Create Ticket Modal Dialog (Fullscreen on Mobile) -->
        <Dialog v-model:open="isCreateModalOpen">
            <DialogContent class="w-full h-full max-w-full max-h-full rounded-none top-0 left-0 translate-x-0 translate-y-0 p-4 sm:p-6 overflow-y-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:max-w-[700px] sm:max-h-[90vh] sm:h-auto sm:rounded-xl">
                <DialogHeader class="pb-2 border-b border-slate-100 sm:border-none">
                    <DialogTitle class="text-lg sm:text-xl font-bold text-slate-900">Buat Laporan Tiket Gangguan</DialogTitle>
                    <DialogDescription class="text-xs sm:text-sm text-slate-500">
                        Lengkapi detail permasalahan jaringan berikut ini untuk diteruskan ke tim teknisi Kominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreateTicket" class="space-y-4 pt-1 sm:pt-2">

                    <!-- On-Behalf Selection (Admin Only) -->
                    <div v-if="canCreateOnBehalf" class="p-3.5 bg-amber-50/70 border border-amber-200 rounded-lg space-y-3.5">
                        <div class="text-xs font-semibold text-amber-950 border-b border-amber-200 pb-1.5">
                            Pengaturan On-Behalf & Disposisi Tim (Admin)
                        </div>

                        <div>
                            <InputLabel for="department_id" value="Instansi / OPD Pelapor *" class="text-amber-950 text-xs font-medium" />
                            <Select v-model="form.department_id">
                                <SelectTrigger class="bg-white mt-1">
                                    <SelectValue placeholder="Pilih OPD Terkait" />
                                </SelectTrigger>
                                <SelectContent class="max-h-56">
                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.department_id" class="mt-1" />
                        </div>

                        <!-- Network Type Card Selector -->
                        <div>
                            <InputLabel value="Tipe Infrastruktur Jaringan *" class="text-amber-950 text-xs font-medium mb-1" />
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="handleNetworkChange('fiber_optic')"
                                    :class="[
                                        'flex flex-col items-center justify-center p-2 rounded-lg border text-center transition-all text-xs font-medium',
                                        form.network_type === 'fiber_optic' 
                                            ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' 
                                            : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'
                                    ]"
                                >
                                    <Cable class="w-4 h-4 mb-1 text-purple-600" />
                                    <span>Fiber Optic</span>
                                </button>

                                <button
                                    type="button"
                                    @click="handleNetworkChange('lan')"
                                    :class="[
                                        'flex flex-col items-center justify-center p-2 rounded-lg border text-center transition-all text-xs font-medium',
                                        form.network_type === 'lan' 
                                            ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' 
                                            : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'
                                    ]"
                                >
                                    <Network class="w-4 h-4 mb-1 text-cyan-600" />
                                    <span>LAN</span>
                                </button>

                                <button
                                    type="button"
                                    @click="handleNetworkChange('wifi')"
                                    :class="[
                                        'flex flex-col items-center justify-center p-2 rounded-lg border text-center transition-all text-xs font-medium',
                                        form.network_type === 'wifi' 
                                            ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' 
                                            : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'
                                    ]"
                                >
                                    <Wifi class="w-4 h-4 mb-1 text-sky-600" />
                                    <span>WiFi</span>
                                </button>
                            </div>
                            <InputError :message="form.errors.network_type" class="mt-1" />
                        </div>

                        <!-- Category & Priority Row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel for="category_id" value="Kategori Masalah *" class="text-amber-950 text-xs font-medium" />
                                <Select 
                                    v-model="form.category_id" 
                                    :disabled="!form.network_type"
                                >
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue :placeholder="form.network_type ? 'Pilih Kategori' : 'Pilih tipe jaringan dahulu'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="cat in availableCategories" 
                                            :key="cat.id" 
                                            :value="cat.id.toString()"
                                        >
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.category_id" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="priority" value="Tingkat Prioritas *" class="text-amber-950 text-xs font-medium" />
                                <Select v-model="form.priority">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Prioritas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Rendah (Low)</SelectItem>
                                        <SelectItem value="medium">Sedang (Medium)</SelectItem>
                                        <SelectItem value="high">Tinggi (High)</SelectItem>
                                        <SelectItem value="emergency">Darurat (Emergency)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.priority" class="mt-1" />
                            </div>
                        </div>

                        <!-- Multi-select Tim Teknisi -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <InputLabel value="Tim Teknisi Penanggung Jawab *" class="text-amber-950 text-xs font-medium" />
                                <span v-if="form.technician_ids.length > 0" class="text-[11px] font-semibold text-amber-800">
                                    {{ form.technician_ids.length }} teknisi dipilih
                                </span>
                            </div>

                            <div class="bg-white border border-amber-200 rounded-lg p-2 max-h-40 overflow-y-auto space-y-1 divide-y divide-slate-100">
                                <div v-if="!technicians || technicians.length === 0" class="text-xs text-slate-500 italic p-2 text-center">
                                    Belum ada data teknisi aktif yang tersedia.
                                </div>
                                <label 
                                    v-for="tech in technicians" 
                                    :key="tech.id"
                                    class="flex items-center justify-between text-xs text-slate-800 cursor-pointer hover:bg-slate-50 p-1.5 rounded transition-colors"
                                >
                                    <div class="flex items-center gap-2">
                                        <input 
                                            type="checkbox" 
                                            :checked="form.technician_ids.includes(tech.id)"
                                            @change="toggleTechnician(tech.id)"
                                            class="rounded border-slate-300 text-kominfo-primary focus:ring-0 focus:ring-offset-0 focus:outline-none outline-none w-4 h-4 cursor-pointer"
                                        />
                                        <span :class="form.technician_ids.includes(tech.id) ? 'font-medium text-slate-900' : 'text-slate-700'">{{ tech.name }}</span>
                                        <span v-if="form.technician_ids[0] === tech.id" class="text-[10px] bg-amber-100 text-amber-900 font-bold px-1.5 py-0.5 rounded">
                                            Lead
                                        </span>
                                    </div>
                                    <span v-if="tech.phone_number" class="text-slate-400 font-mono text-[11px]">
                                        {{ tech.phone_number }}
                                    </span>
                                </label>
                            </div>
                            <InputError :message="form.errors.technician_ids" class="mt-1" />
                        </div>
                    </div>

                    <!-- Title -->
                    <div>
                        <InputLabel for="title" value="Subjek / Ringkasan Kendala *" />
                        <Input id="title" v-model="form.title" placeholder="Cth: Internet mati di ruang bidang informasi" class="mt-1" />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <!-- Location Details -->
                    <div>
                        <InputLabel for="location_details" value="Lokasi Detail / Ruangan *" />
                        <Input id="location_details" v-model="form.location_details" placeholder="Cth: Gedung B Lantai 2, Ruang Rapat" class="mt-1" />
                        <InputError :message="form.errors.location_details" class="mt-1" />
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Deskripsi Detail Kendala *" />
                        <Textarea 
                            id="description" 
                            v-model="form.description" 
                            rows="3" 
                            placeholder="Jelaskan kendala apa yang dialami, sejak kapan, dan dampaknya..." 
                            class="mt-1"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <!-- Attachments -->
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Lampiran Bukti Foto" />
                            <span class="text-xs text-slate-400 font-normal italic">(Opsional - Maks. 3 File)</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto perangkat atau pesan error jika ada.</p>
                        <FileUpload 
                            v-model="form.attachments"
                            :multiple="true"
                            :maxFiles="3"
                            :maxSizeMB="5"
                            @error="(msg) => form.errors.attachments = msg"
                        />
                        <InputError :message="form.errors.attachments" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 pb-2 border-t border-slate-100 sticky bottom-0 bg-white sm:static">
                        <Button type="button" variant="outline" @click="isCreateModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ form.processing ? 'Mengirim Laporan...' : (canCreateOnBehalf ? 'Terbitkan & Tugaskan' : 'Kirim Laporan Gangguan') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>

