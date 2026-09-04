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
    departments: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'departments' ? (props.filters?.search || '') : '');

const columns = [
    { key: 'code', label: 'Kode OPD', sortable: true },
    { key: 'name', label: 'Nama Instansi', sortable: true },
    { key: 'operator', label: 'Operator / PIC', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'departments',
        search: searchQuery.value,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'departments',
        search: searchQuery.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        dept_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    router.get(route('admin.master-data.index'), {
        tab: 'departments',
        search: value,
        dept_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const departmentToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    code: '',
    name: '',
    address: '',
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (department: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = department.id;
    form.code = department.code;
    form.name = department.name;
    form.address = department.address;
    form.status = department.status;
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value) {
        form.put(route('admin.departments.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.departments.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const openDeleteDialog = (id: number) => {
    departmentToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (departmentToDelete.value) {
        router.delete(route('admin.departments.destroy', departmentToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                departmentToDelete.value = null;
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
            :data="departments"
            :modelValue="searchQuery"
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
            searchPlaceholder="Cari OPD atau kode singkatan..."
        >
            <template #actions>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah OPD
                </Button>
            </template>

            <!-- Custom Cell Rendering (No badges, consistent typography) -->
            <template #cell-code="{ item }">
                <span class="font-mono text-xs text-slate-700">{{ item.code }}</span>
            </template>

            <template #cell-name="{ item }">
                <span class="font-medium text-slate-900">{{ item.name }}</span>
            </template>

            <template #cell-operator="{ item }">
                <div v-if="item.operator">
                    <div class="font-medium text-slate-900">{{ item.operator.name }}</div>
                    <div class="text-xs text-slate-500">{{ item.operator.phone_number || '-' }}</div>
                </div>
                <span v-else class="text-slate-400 italic">-</span>
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
                        title="Edit OPD"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus OPD"
                        @click="openDeleteDialog(item.id)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="w-[95vw] sm:max-w-[500px]">
                <DialogHeader class="pb-1">
                    <DialogTitle class="text-lg font-semibold text-slate-900">{{ isEditMode ? 'Edit Data OPD' : 'Tambah OPD Baru' }}</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Isi form di bawah ini untuk mengelola data instansi/OPD yang terdaftar di Kominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-3">
                    <div class="grid gap-3">
                        <div class="space-y-1">
                            <InputLabel for="code" value="Kode Singkatan OPD" class="text-xs" />
                            <Input id="code" v-model="form.code" placeholder="Cth: DINKES" :disabled="isEditMode" class="h-9 text-sm" />
                            <InputError :message="form.errors.code" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="name" value="Nama Instansi Lengkap" class="text-xs" />
                            <Input id="name" v-model="form.name" placeholder="Cth: Dinas Kesehatan Kota Palu" class="h-9 text-sm" />
                            <InputError :message="form.errors.name" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="address" value="Alamat Lengkap" class="text-xs" />
                            <Textarea id="address" v-model="form.address" placeholder="Masukkan alamat instansi" class="text-sm min-h-[70px] max-h-[100px]" />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="status" value="Status Instansi" class="text-xs" />
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
                            {{ isEditMode ? 'Simpan Perubahan' : 'Tambah OPD' }}
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
                        Apakah Anda yakin ingin menghapus data OPD ini? Data yang dihapus tidak akan ditampilkan lagi (namun tetap tersimpan sebagai arsip).
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-5 flex flex-row justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white inline-flex items-center gap-1.5" @click="confirmDelete">
                        <Trash2 class="w-4 h-4" />
                        Ya, Hapus Data
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
