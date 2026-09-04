<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import DataTable from '@/Components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit2, Trash2 } from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    categories: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'categories' ? (props.filters?.search || '') : '');
const infrastructureFilter = ref(
    (props.filters?.tab === 'categories' || !props.filters?.tab) 
        ? (props.filters?.infrastructure_type || props.filters?.network_type || 'all') 
        : 'all'
);

const columns = [
    { key: 'name', label: 'Nama Kategori', sortable: true },
    { key: 'infrastructure_type', label: 'Jenis Infrastruktur', sortable: true },
    { key: 'sla_hours', label: 'Target SLA (Jam)', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'categories',
        search: searchQuery.value,
        infrastructure_type: infrastructureFilter.value,
        network_type: infrastructureFilter.value,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'categories',
        search: searchQuery.value,
        infrastructure_type: infrastructureFilter.value,
        network_type: infrastructureFilter.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        cat_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    searchQuery.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'categories',
        search: value,
        infrastructure_type: infrastructureFilter.value,
        network_type: infrastructureFilter.value,
        cat_page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleInfrastructureFilter = (value: string) => {
    infrastructureFilter.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'categories',
        search: searchQuery.value,
        infrastructure_type: value,
        network_type: value,
        cat_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const categoryToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    infrastructure_type: 'Fiber optic',
    network_type: 'Fiber optic',
    sla_hours: 24,
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    form.infrastructure_type = 'Fiber optic';
    form.network_type = 'Fiber optic';
    form.sla_hours = 24;
    form.status = 'active';
    isModalOpen.value = true;
};

const openEditModal = (category: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = category.id;
    form.name = category.name;
    form.infrastructure_type = category.infrastructure_type || category.network_type || 'Fiber optic';
    form.network_type = form.infrastructure_type;
    form.sla_hours = category.sla_hours;
    form.status = category.status;
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value) {
        form.put(route('admin.categories.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.categories.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const openDeleteDialog = (id: number) => {
    categoryToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (categoryToDelete.value) {
        router.delete(route('admin.categories.destroy', categoryToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                categoryToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- DataTable Component -->
        <DataTable 
            :columns="columns" 
            :data="categories"
            :modelValue="searchQuery"
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
            searchPlaceholder="Cari nama kategori..."
        >
            <template #filters>
                <Select :modelValue="infrastructureFilter" @update:modelValue="handleInfrastructureFilter">
                    <SelectTrigger class="w-full sm:w-[180px] bg-white text-sm h-10">
                        <SelectValue placeholder="Semua Jenis" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Jenis</SelectItem>
                        <SelectItem value="Fiber optic">Fiber optic</SelectItem>
                        <SelectItem value="Perangkat/Akses">Perangkat/Akses</SelectItem>
                        <SelectItem value="Power/poe">Power/poe</SelectItem>
                        <SelectItem value="Converter">Converter</SelectItem>
                        <SelectItem value="Layanan/jaringan">Layanan/jaringan</SelectItem>
                    </SelectContent>
                </Select>
            </template>

            <template #actions>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah Kategori
                </Button>
            </template>

            <!-- Custom Cell Rendering (No badges, consistent typography) -->
            <template #cell-name="{ item }">
                <span class="font-medium text-slate-900">{{ item.name }}</span>
            </template>

            <template #cell-infrastructure_type="{ item }">
                <span class="text-xs sm:text-sm text-slate-700">{{ item.infrastructure_type || item.network_type || '-' }}</span>
            </template>

            <template #cell-network_type="{ item }">
                <span class="text-xs sm:text-sm text-slate-700">{{ item.infrastructure_type || item.network_type || '-' }}</span>
            </template>

            <template #cell-sla_hours="{ item }">
                <span class="text-xs sm:text-sm text-slate-700">{{ item.sla_hours }} Jam</span>
            </template>

            <template #cell-status="{ item }">
                <span 
                    class="text-xs sm:text-sm font-medium"
                    :class="item.status === 'active' ? 'text-emerald-600' : 'text-slate-400'"
                >
                    {{ item.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </span>
            </template>

            <template #actions-cell="{ item }">
                <div class="flex items-center justify-end space-x-1">
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-kominfo-primary hover:bg-slate-100" 
                        title="Edit Kategori"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus Kategori"
                        @click="openDeleteDialog(item.id)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="w-[95vw] sm:max-w-[425px]">
                <DialogHeader class="pb-1">
                    <DialogTitle class="text-lg font-semibold text-slate-900">{{ isEditMode ? 'Edit Kategori' : 'Tambah Kategori Baru' }}</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Konfigurasi nama gangguan, jenis infrastruktur jaringan, dan estimasi waktu penanganan (SLA).
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-3">
                    <div class="grid gap-3">
                        <div class="space-y-1">
                            <InputLabel for="name" value="Nama Gangguan / Kategori" class="text-xs" />
                            <Input id="name" v-model="form.name" placeholder="Cth: Kabel Fiber Optik Putus" class="h-9 text-sm" />
                            <InputError :message="form.errors.name" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="infrastructure_type" value="Jenis Infrastruktur" class="text-xs" />
                            <Select :modelValue="form.infrastructure_type" @update:modelValue="(v) => { form.infrastructure_type = v; form.network_type = v; }">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih jenis infrastruktur" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="Fiber optic">Fiber optic</SelectItem>
                                    <SelectItem value="Perangkat/Akses">Perangkat/Akses</SelectItem>
                                    <SelectItem value="Power/poe">Power/poe</SelectItem>
                                    <SelectItem value="Converter">Converter</SelectItem>
                                    <SelectItem value="Layanan/jaringan">Layanan/jaringan</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.infrastructure_type || form.errors.network_type" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="sla_hours" value="Target SLA (dalam Jam)" class="text-xs" />
                            <Input id="sla_hours" type="number" min="1" max="720" v-model="form.sla_hours" placeholder="Cth: 24" class="h-9 text-sm" />
                            <InputError :message="form.errors.sla_hours" />
                            <p class="text-[11px] text-slate-500">Batas waktu perbaikan sebelum tiket dianggap overdue.</p>
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="status" value="Status Kategori" class="text-xs" />
                            <Select v-model="form.status">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Aktif</SelectItem>
                                    <SelectItem value="inactive">Nonaktif</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status" />
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" variant="outline" size="sm" @click="isModalOpen = false">Batal</Button>
                        <Button type="submit" size="sm" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah Kategori' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus kategori gangguan ini? Data yang terhapus tidak bisa dipilih untuk tiket laporan baru.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-5 flex flex-row justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white inline-flex items-center gap-1.5" @click="confirmDelete">
                        <Trash2 class="w-4 h-4" />
                        Ya, Hapus Kategori
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
