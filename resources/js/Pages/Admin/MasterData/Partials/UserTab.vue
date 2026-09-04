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
    users: any;
    departments: any[];
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'users' ? (props.filters?.search || '') : '');
const roleFilter = ref(props.filters?.tab === 'users' ? (props.filters?.role || 'all') : 'all');

const columns = [
    { key: 'name', label: 'Pengguna', sortable: true },
    { key: 'role', label: 'Peran / Role', sortable: true },
    { key: 'department', label: 'Instansi / OPD', sortable: false },
    { key: 'status', label: 'Status Akun', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'users',
        search: searchQuery.value,
        role: roleFilter.value,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'users',
        search: searchQuery.value,
        role: roleFilter.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        user_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    router.get(route('admin.master-data.index'), {
        tab: 'users',
        search: value,
        role: roleFilter.value,
        user_page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleRoleFilter = (value: string) => {
    roleFilter.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'users',
        search: searchQuery.value,
        role: value,
        user_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const userToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    email: '',
    password: '',
    role: 'opd_user',
    department_id: '' as string | number,
    phone_number: '',
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    form.role = 'opd_user';
    form.department_id = props.departments.length > 0 ? props.departments[0].id : '';
    form.status = 'active';
    isModalOpen.value = true;
};

const openEditModal = (user: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = ''; // empty password on edit
    form.role = user.role;
    form.department_id = user.department_id || '';
    form.phone_number = user.phone_number || '';
    form.status = user.status;
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value) {
        form.put(route('admin.users.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.users.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const openDeleteDialog = (id: number) => {
    userToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (userToDelete.value) {
        router.delete(route('admin.users.destroy', userToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                userToDelete.value = null;
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
            :data="users"
            :modelValue="searchQuery"
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
            searchPlaceholder="Cari nama, email, no HP..."
        >
            <template #filters>
                <Select :modelValue="roleFilter" @update:modelValue="handleRoleFilter">
                    <SelectTrigger class="w-full sm:w-[180px] bg-white text-sm h-10">
                        <SelectValue placeholder="Semua Peran" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Peran</SelectItem>
                        <SelectItem value="admin">Administrator</SelectItem>
                        <SelectItem value="technician">Teknisi Jaringan</SelectItem>
                        <SelectItem value="opd_user">Operator OPD</SelectItem>
                    </SelectContent>
                </Select>
            </template>

            <template #actions>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah Pengguna
                </Button>
            </template>

            <!-- Custom Cell Rendering (No badges, consistent typography) -->
            <template #cell-name="{ item }">
                <div>
                    <div class="font-medium text-slate-900">{{ item.name }}</div>
                    <div class="text-xs text-slate-500">{{ item.email }}</div>
                    <div class="text-xs text-slate-400" v-if="item.phone_number">{{ item.phone_number }}</div>
                </div>
            </template>

            <template #cell-role="{ item }">
                <span class="text-xs sm:text-sm text-slate-700 font-medium">
                    {{ item.role === 'admin' ? 'Administrator' : (item.role === 'technician' ? 'Teknisi Jaringan' : 'Operator OPD') }}
                </span>
            </template>

            <template #cell-department="{ item }">
                <span v-if="item.department" class="text-xs sm:text-sm text-slate-700">{{ item.department.name }}</span>
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
                        title="Edit Pengguna"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        v-if="item.id !== $page.props.auth.user.id" 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus Pengguna"
                        @click="openDeleteDialog(item.id)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="w-[95vw] sm:max-w-[620px]">
                <DialogHeader class="pb-1">
                    <DialogTitle class="text-lg font-semibold text-slate-900">{{ isEditMode ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Kelola akun pengguna, hak akses peran sistem, dan instansi penempatan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        <div class="space-y-1">
                            <InputLabel for="name" value="Nama Lengkap" class="text-xs" />
                            <Input id="name" v-model="form.name" placeholder="Cth: Budi Pratama" class="h-9 text-sm" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="email" value="Alamat Email" class="text-xs" />
                            <Input id="email" type="email" v-model="form.email" placeholder="Cth: budi@palukota.go.id" class="h-9 text-sm" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="phone_number" value="No. WhatsApp" class="text-xs" />
                            <Input id="phone_number" v-model="form.phone_number" placeholder="Cth: 081234567890" class="h-9 text-sm" />
                            <InputError :message="form.errors.phone_number" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="password" :value="isEditMode ? 'Password Baru (Opsional)' : 'Kata Sandi'" class="text-xs" />
                            <Input id="password" type="password" v-model="form.password" placeholder="Minimal 8 karakter" class="h-9 text-sm" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="role" value="Peran / Role" class="text-xs" />
                            <Select v-model="form.role">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="opd_user">Operator OPD</SelectItem>
                                    <SelectItem value="technician">Teknisi Jaringan</SelectItem>
                                    <SelectItem value="admin">Administrator</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.role" />
                        </div>

                        <div class="space-y-1">
                            <InputLabel for="status" value="Status Akun" class="text-xs" />
                            <Select v-model="form.status">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Aktif</SelectItem>
                                    <SelectItem value="inactive">Nonaktif</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.status" />
                        </div>

                        <div v-if="form.role === 'opd_user'" class="col-span-1 sm:col-span-2 space-y-1">
                            <InputLabel for="department_id" value="Instansi / OPD Asal" class="text-xs" />
                            <Select v-model="form.department_id">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih OPD Terdaftar" />
                                </SelectTrigger>
                                <SelectContent class="max-h-52">
                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id">
                                        {{ dept.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.department_id" />
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button type="button" variant="outline" size="sm" @click="isModalOpen = false">Batal</Button>
                        <Button type="submit" size="sm" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ isEditMode ? 'Simpan Perubahan' : 'Daftarkan Pengguna' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus User</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus akun pengguna ini? Pengguna tidak akan dapat mengakses sistem kembali.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-5 flex flex-row justify-end gap-2">
                    <Button type="button" variant="outline" size="sm" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white inline-flex items-center gap-1.5" @click="confirmDelete">
                        <Trash2 class="w-4 h-4" />
                        Ya, Hapus Pengguna
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
