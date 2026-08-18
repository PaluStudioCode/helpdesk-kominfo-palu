<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
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

const searchQuery = ref(props.filters?.search || '');

const columns = [
    { key: 'name', label: 'Nama Kategori', sortable: true },
    { key: 'network_type', label: 'Tipe Jaringan', sortable: true },
    { key: 'sla_hours', label: 'Target SLA (Jam)', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.categories.index'), {
        ...props.filters,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.categories.index'), {
        ...props.filters,
        page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    router.get(route('admin.categories.index'), {
        ...props.filters,
        search: value,
        page: 1
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
    network_type: 'fiber_optic',
    sla_hours: 24,
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (category: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = category.id;
    form.name = category.name;
    form.network_type = category.network_type;
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
    <Head title="Master Data Kategori" />

    <AuthenticatedLayout>
        <template #header>
            Master Kategori Gangguan
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md p-4 mb-4">
                {{ $page.props.flash.success }}
            </div>

            <DataTable 
                :columns="columns" 
                :data="categories"
                :modelValue="searchQuery"
                @update:modelValue="handleSearch"
                @sort="handleSort"
                @page="handlePage"
                searchPlaceholder="Cari kategori..."
            >
                <template #actions>
                    <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                        <Plus class="w-4 h-4 mr-2" /> Tambah Kategori
                    </Button>
                </template>

                <template #cell-network_type="{ item }">
                    <StatusBadge type="network" :status="item.network_type" />
                </template>
                
                <template #cell-sla_hours="{ item }">
                    <span class="font-medium text-slate-700">{{ item.sla_hours }} Jam</span>
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge :status="item.status" />
                </template>

                <template #actions-cell="{ item }">
                    <div class="flex items-center space-x-2">
                        <Button variant="ghost" size="icon" @click="openEditModal(item)">
                            <Edit2 class="w-4 h-4 text-slate-500" />
                        </Button>
                        <Button variant="ghost" size="icon" @click="openDeleteDialog(item.id)">
                            <Trash2 class="w-4 h-4 text-red-500" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditMode ? 'Edit Kategori' : 'Tambah Kategori Baru' }}</DialogTitle>
                    <DialogDescription>
                        Konfigurasi kategori masalah beserta tipe jaringan dan target SLA-nya.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid gap-4">
                        <div>
                            <InputLabel for="name" value="Nama Kategori Gangguan" />
                            <Input id="name" v-model="form.name" placeholder="Cth: Kabel Fiber Optic Putus" />
                            <InputError :message="form.errors.name" />
                        </div>
                        
                        <div>
                            <InputLabel for="network_type" value="Tipe Jaringan Utama" />
                            <Select v-model="form.network_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tipe jaringan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fiber_optic">Fiber Optic</SelectItem>
                                    <SelectItem value="lan">Jaringan LAN</SelectItem>
                                    <SelectItem value="wifi">Jaringan WiFi</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.network_type" />
                        </div>
                        
                        <div>
                            <InputLabel for="sla_hours" value="Target Waktu Penanganan / SLA (dalam Jam)" />
                            <Input id="sla_hours" type="number" min="1" v-model="form.sla_hours" />
                            <InputError :message="form.errors.sla_hours" />
                        </div>

                        <div>
                            <InputLabel for="status" value="Status" />
                            <Select v-model="form.status">
                                <SelectTrigger>
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

                    <DialogFooter class="mt-6">
                        <Button type="button" variant="outline" @click="isModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ isEditMode ? 'Simpan' : 'Tambah Kategori' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus kategori ini? Data yang dihapus tidak akan ditampilkan lagi (namun tetap tersimpan sebagai arsip).
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button variant="destructive" @click="confirmDelete">Ya, Hapus Kategori</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>