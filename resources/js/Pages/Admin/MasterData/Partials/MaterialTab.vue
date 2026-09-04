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
import { Textarea } from '@/components/ui/textarea';
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
    materials: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'materials' ? (props.filters?.search || '') : '');

const standardUnits = [
    'pcs',
    'meter',
    'unit',
    'roll',
    'set',
    'batang',
    'buah',
    'pack',
    'box',
];

const columns = [
    { key: 'name', label: 'Nama Material / Perangkat', sortable: true },
    { key: 'default_unit', label: 'Satuan Standar', sortable: true },
    { key: 'description', label: 'Deskripsi / Spesifikasi', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'materials',
        search: searchQuery.value,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'materials',
        search: searchQuery.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        mat_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    searchQuery.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'materials',
        search: value,
        mat_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const materialToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    default_unit: 'pcs',
    description: '',
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    form.default_unit = 'pcs';
    form.status = 'active';
    isModalOpen.value = true;
};

const openEditModal = (material: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = material.id;
    form.name = material.name;
    form.default_unit = material.default_unit || 'pcs';
    form.description = material.description || '';
    form.status = material.status;
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value) {
        form.put(route('admin.materials.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.materials.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const confirmDelete = (id: number) => {
    materialToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const deleteMaterial = () => {
    if (materialToDelete.value) {
        router.delete(route('admin.materials.destroy', materialToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                materialToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <div class="space-y-4">
        <DataTable
            :columns="columns"
            :data="materials"
            :modelValue="searchQuery"
            searchPlaceholder="Cari nama material atau suku cadang..."
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
        >
            <template #actions>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah Material / Suku Cadang
                </Button>
            </template>

            <!-- Custom Cell Rendering (No badges, consistent typography) -->
            <template #cell-name="{ item }">
                <span class="font-medium text-slate-900">{{ item.name }}</span>
            </template>

            <template #cell-default_unit="{ item }">
                <span class="text-xs sm:text-sm text-slate-700">{{ item.default_unit }}</span>
            </template>

            <template #cell-description="{ item }">
                <span class="text-xs sm:text-sm text-slate-600 line-clamp-1">{{ item.description || '-' }}</span>
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
                        title="Edit Material"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus Material"
                        @click="confirmDelete(item.id)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditMode ? 'Edit Material / Suku Cadang' : 'Tambah Material / Suku Cadang Baru' }}</DialogTitle>
                    <DialogDescription>
                        Daftar material ini akan muncul pada opsi pilihan teknisi saat mencatat komponen/perangkat yang digunakan di lapangan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 py-2">
                    <div>
                        <InputLabel for="name" value="Nama Material / Perangkat *" />
                        <Input 
                            id="name" 
                            v-model="form.name" 
                            placeholder="Cth: Patch Cord UTP Cat6, Fast Connector SC, Kabel Drop Core" 
                            class="mt-1" 
                            required 
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="default_unit" value="Satuan Standar *" />
                        <Select v-model="form.default_unit">
                            <SelectTrigger class="mt-1 bg-white">
                                <SelectValue placeholder="Pilih Satuan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in standardUnits" :key="u" :value="u">
                                    {{ u }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.default_unit" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Deskripsi / Spesifikasi (Opsional)" />
                        <Textarea 
                            id="description" 
                            v-model="form.description" 
                            placeholder="Keterangan spesifikasi teknis komponen atau suku cadang..." 
                            rows="2" 
                            class="mt-1 text-sm bg-white" 
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="status" value="Status Operasional *" />
                        <Select v-model="form.status">
                            <SelectTrigger class="mt-1 bg-white">
                                <SelectValue placeholder="Pilih Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif</SelectItem>
                                <SelectItem value="inactive">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <DialogFooter class="mt-5">
                        <Button type="button" variant="outline" @click="isModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white">
                            {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Perbarui Material' : 'Simpan Material') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle>Hapus Material / Suku Cadang</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus material ini? Material yang telah dihapus tidak akan muncul lagi di opsi pilihan teknisi.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4">
                    <Button type="button" variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" @click="deleteMaterial">Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
