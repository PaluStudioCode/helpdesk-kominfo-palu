<script setup lang="ts">
import { ref, watch } from 'vue';
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

const searchQuery = ref(props.filters?.search || '');

const columns = [
    { key: 'code', label: 'Kode OPD', sortable: true },
    { key: 'name', label: 'Nama Instansi', sortable: true },
    { key: 'pic_name', label: 'PIC', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.departments.index'), {
        ...props.filters,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.departments.index'), {
        ...props.filters,
        page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    router.get(route('admin.departments.index'), {
        ...props.filters,
        search: value,
        page: 1
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
    pic_name: '',
    pic_phone: '',
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
    form.pic_name = department.pic_name || '';
    form.pic_phone = department.pic_phone || '';
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
    <Head title="Master Data OPD" />

    <AuthenticatedLayout>
        <template #header>
            Master Data OPD / Instansi
        </template>

        <div class="space-y-6">
            <!-- Flash Message (akan diganti toast nanti) -->
            <div v-if="$page.props.flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md p-4 mb-4">
                {{ $page.props.flash.success }}
            </div>

            <!-- DataTable Component -->
            <DataTable 
                :columns="columns" 
                :data="departments"
                :modelValue="searchQuery"
                @update:modelValue="handleSearch"
                @sort="handleSort"
                @page="handlePage"
                searchPlaceholder="Cari OPD..."
            >
                <template #actions>
                    <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                        <Plus class="w-4 h-4 mr-2" /> Tambah OPD
                    </Button>
                </template>

                <!-- Custom Cell Rendering -->
                <template #cell-status="{ item }">
                    <StatusBadge :status="item.status" />
                </template>

                <template #cell-pic_name="{ item }">
                    <div v-if="item.pic_name">
                        <div class="font-medium text-slate-900">{{ item.pic_name }}</div>
                        <div class="text-xs text-slate-500">{{ item.pic_phone }}</div>
                    </div>
                    <span v-else class="text-slate-400 italic">-</span>
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
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditMode ? 'Edit Data OPD' : 'Tambah OPD Baru' }}</DialogTitle>
                    <DialogDescription>
                        Isi form di bawah ini untuk mengelola data instansi/OPD yang terdaftar di Kominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div class="grid gap-4">
                        <div>
                            <InputLabel for="code" value="Kode Singkatan OPD" />
                            <Input id="code" v-model="form.code" placeholder="Cth: DINKES" :disabled="isEditMode" />
                            <InputError :message="form.errors.code" />
                        </div>
                        
                        <div>
                            <InputLabel for="name" value="Nama Instansi Lengkap" />
                            <Input id="name" v-model="form.name" placeholder="Cth: Dinas Kesehatan Kota Palu" />
                            <InputError :message="form.errors.name" />
                        </div>
                        
                        <div>
                            <InputLabel for="address" value="Alamat Lengkap" />
                            <Textarea id="address" v-model="form.address" placeholder="Masukkan alamat instansi" />
                            <InputError :message="form.errors.address" />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="pic_name" value="Nama PIC (Opsional)" />
                                <Input id="pic_name" v-model="form.pic_name" placeholder="Nama pengurus IT" />
                                <InputError :message="form.errors.pic_name" />
                            </div>
                            
                            <div>
                                <InputLabel for="pic_phone" value="No. WhatsApp PIC" />
                                <Input id="pic_phone" v-model="form.pic_phone" placeholder="Cth: 08123456789" />
                                <InputError :message="form.errors.pic_phone" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="status" value="Status Instansi" />
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
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button variant="destructive" @click="confirmDelete">Ya, Hapus Data</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>