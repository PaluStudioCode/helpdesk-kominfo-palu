<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
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
}>();

const currentUser = computed(() => usePage().props.auth.user as any);
const canCreateTicket = computed(() => ['admin', 'opd_user'].includes(currentUser.value?.role));

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const networkFilter = ref(props.filters?.network_type || 'all');

// Create Ticket Modal State & Form
const isCreateModalOpen = ref(false);
const form = useForm({
    network_type: '',
    category_id: '',
    title: '',
    location_details: '',
    description: '',
    priority: 'medium',
    department_id: '',
    attachments: [] as File[],
});

const availableCategories = computed(() => {
    if (!form.network_type || !props.categoriesMap) return [];
    return props.categoriesMap[form.network_type] || [];
});

const handleNetworkChange = (type: string) => {
    form.network_type = type;
    form.category_id = '';
};

const openCreateTicketModal = () => {
    form.reset();
    form.clearErrors();
    form.network_type = '';
    form.category_id = '';
    form.priority = 'medium';
    form.department_id = '';
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

const columns = [
    { key: 'ticket_number', label: 'No. Tiket', sortable: true },
    { key: 'title', label: 'Subjek', sortable: false },
    { key: 'department', label: 'OPD / Instansi', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'sla_status', label: 'SLA', sortable: false },
];

const getSlaStatus = (ticket: any) => {
    if (!ticket.due_at || ['resolved', 'closed', 'cancelled'].includes(ticket.status)) return null;
    
    const now = new Date();
    const dueAt = new Date(ticket.due_at);
    const diffHours = (dueAt.getTime() - now.getTime()) / (1000 * 60 * 60);

    if (diffHours < 0) return { status: 'danger', label: 'Overdue' };
    if (diffHours <= 2) return { status: 'warning', label: 'Mendekati SLA' };
    return { status: 'safe', label: 'Aman' };
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric'
    });
};
</script>

<template>
    <Head title="Antrean Tiket" />

    <AuthenticatedLayout>
        <template #header>
            Antrean Tiket Gangguan
        </template>

        <div class="space-y-6">
            <!-- Header Title and Description -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Antrean & Riwayat Tiket Gangguan</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Pantau status penanganan, tindak lanjuti eskalasi kendala infrastruktur jaringan, serta buat laporan tiket baru.
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
                    <div class="flex items-center gap-2">
                        <Select :modelValue="statusFilter" @update:modelValue="handleStatusFilter">
                            <SelectTrigger class="w-[160px] bg-white">
                                <SelectValue placeholder="Status Tiket" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Status</SelectItem>
                                <SelectItem value="open">Open (Menunggu)</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="resolved">Resolved</SelectItem>
                                <SelectItem value="closed">Closed (Ditutup)</SelectItem>
                                <SelectItem value="mendekati_sla">Mendekati SLA</SelectItem>
                                <SelectItem value="overdue">Overdue SLA</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :modelValue="networkFilter" @update:modelValue="handleNetworkFilter">
                            <SelectTrigger class="w-[150px] bg-white">
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
                    <Button v-if="canCreateTicket" @click="openCreateTicketModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                        <Plus class="w-4 h-4 mr-2" /> {{ canCreateOnBehalf ? 'Buat Tiket (On-Behalf)' : 'Buat Tiket Baru' }}
                    </Button>
                </template>

                <template #cell-ticket_number="{ item }">
                    <div class="font-medium text-slate-900">{{ item.ticket_number }}</div>
                    <div class="text-xs text-slate-500">{{ formatDate(item.created_at) }}</div>
                </template>

                <template #cell-title="{ item }">
                    <div class="font-medium text-slate-900 truncate max-w-xs" :title="item.title">{{ item.title }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <StatusBadge type="network" :status="item.network_type" />
                        <StatusBadge type="priority" :status="item.priority" />
                    </div>
                </template>

                <template #cell-department="{ item }">
                    <span class="text-sm">{{ item.department.name }}</span>
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge type="ticket" :status="item.status" />
                </template>

                <template #cell-sla_status="{ item }">
                    <StatusBadge v-if="getSlaStatus(item)" type="sla" :status="getSlaStatus(item).status" />
                    <span v-else class="text-xs text-slate-400">-</span>
                </template>

                <template #actions-cell="{ item }">
                    <Link :href="route('tickets.show', item.id)">
                        <Button variant="ghost" size="sm" class="h-8 border border-slate-200">
                            <Eye class="w-4 h-4 mr-2" /> Detail
                        </Button>
                    </Link>
                </template>
            </DataTable>
        </div>

        <!-- Create Ticket Modal Dialog -->
        <Dialog v-model:open="isCreateModalOpen">
            <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Buat Laporan Tiket Gangguan</DialogTitle>
                    <DialogDescription>
                        Lengkapi detail permasalahan jaringan berikut ini untuk diteruskan ke tim teknisi Kominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCreateTicket" class="space-y-4 pt-2">
                    <!-- On-Behalf Selection (Admin Only) -->
                    <div v-if="canCreateOnBehalf && departments && departments.length > 0">
                        <InputLabel for="department_id" value="Instansi / OPD Pelapor (On-Behalf)" />
                        <Select v-model="form.department_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih OPD Terkait" />
                            </SelectTrigger>
                            <SelectContent class="max-h-56">
                                <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                    {{ dept.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.department_id" />
                    </div>

                    <!-- Network Type Card Selector -->
                    <div>
                        <InputLabel value="Tipe Infrastruktur Jaringan" />
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-1.5">
                            <button
                                type="button"
                                @click="handleNetworkChange('fiber_optic')"
                                :class="[
                                    'flex flex-col items-center justify-center p-3 rounded-lg border-2 text-center transition-all',
                                    form.network_type === 'fiber_optic' 
                                        ? 'border-kominfo-primary bg-blue-50/50 text-kominfo-primary font-semibold ring-2 ring-kominfo-primary/20' 
                                        : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'
                                ]"
                            >
                                <Cable class="w-6 h-6 mb-1.5" />
                                <span class="text-xs">Fiber Optic</span>
                            </button>

                            <button
                                type="button"
                                @click="handleNetworkChange('lan')"
                                :class="[
                                    'flex flex-col items-center justify-center p-3 rounded-lg border-2 text-center transition-all',
                                    form.network_type === 'lan' 
                                        ? 'border-kominfo-primary bg-blue-50/50 text-kominfo-primary font-semibold ring-2 ring-kominfo-primary/20' 
                                        : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'
                                ]"
                            >
                                <Network class="w-6 h-6 mb-1.5" />
                                <span class="text-xs">Jaringan LAN</span>
                            </button>

                            <button
                                type="button"
                                @click="handleNetworkChange('wifi')"
                                :class="[
                                    'flex flex-col items-center justify-center p-3 rounded-lg border-2 text-center transition-all',
                                    form.network_type === 'wifi' 
                                        ? 'border-kominfo-primary bg-blue-50/50 text-kominfo-primary font-semibold ring-2 ring-kominfo-primary/20' 
                                        : 'border-slate-200 hover:border-slate-300 text-slate-600 bg-white'
                                ]"
                            >
                                <Wifi class="w-6 h-6 mb-1.5" />
                                <span class="text-xs">WiFi / Nirkabel</span>
                            </button>
                        </div>
                        <InputError :message="form.errors.network_type" />
                    </div>

                    <!-- Category & Priority Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="category_id" value="Kategori Gangguan" />
                            <Select 
                                v-model="form.category_id" 
                                :disabled="!form.network_type"
                            >
                                <SelectTrigger>
                                    <SelectValue :placeholder="form.network_type ? 'Pilih Kategori' : 'Pilih tipe jaringan dahulu'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem 
                                        v-for="cat in availableCategories" 
                                        :key="cat.id" 
                                        :value="cat.id"
                                    >
                                        {{ cat.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <div>
                            <InputLabel for="priority" value="Tingkat Urgensi / Prioritas" />
                            <Select v-model="form.priority">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Tingkat Prioritas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Rendah (Low)</SelectItem>
                                    <SelectItem value="medium">Normal / Sedang (Medium)</SelectItem>
                                    <SelectItem value="high">Tinggi (High)</SelectItem>
                                    <SelectItem v-if="currentUser?.role === 'admin'" value="emergency">Darurat (Emergency - Admin)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.priority" />
                        </div>
                    </div>

                    <!-- Title -->
                    <div>
                        <InputLabel for="title" value="Judul / Subjek Ringkas Kendala" />
                        <Input id="title" v-model="form.title" placeholder="Cth: Akses Internet Ruang Rapat Mati" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <!-- Location Details -->
                    <div>
                        <InputLabel for="location_details" value="Lokasi Detail / Ruangan" />
                        <Input id="location_details" v-model="form.location_details" placeholder="Cth: Gedung B Lantai 2, Ruang Kepala Dinas" />
                        <InputError :message="form.errors.location_details" />
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Deskripsi Lengkap Gangguan" />
                        <Textarea 
                            id="description" 
                            v-model="form.description" 
                            rows="3" 
                            placeholder="Jelaskan kronologi, sejak kapan kendala terjadi, atau tindakan yang sudah dicoba..." 
                        />
                        <InputError :message="form.errors.description" />
                    </div>

                    <!-- Attachments -->
                    <div>
                        <InputLabel value="Lampiran Bukti Foto / Dokumen Pendukung" />
                        <FileUpload 
                            v-model="form.attachments"
                            :maxFiles="3"
                            :maxSizeMB="5"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.attachments" />
                    </div>

                    <DialogFooter class="pt-4 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isCreateModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ form.processing ? 'Mengirim Laporan...' : 'Kirim Laporan Tiket' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>

