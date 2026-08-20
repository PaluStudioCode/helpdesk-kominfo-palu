<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
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
    departments: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || 'all');

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
    
    router.get(route('admin.users.index'), {
        ...props.filters,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.users.index'), {
        ...props.filters,
        page
    }, { preserveState: true });
};

const applyFilters = () => {
    router.get(route('admin.users.index'), {
        ...props.filters,
        search: searchQuery.value,
        role: roleFilter.value,
        page: 1
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
    phone_number: '',
    role: 'opd_user',
    department_id: null as number | null,
    status: 'active'
});

const isOpdUser = computed(() => form.role === 'opd_user');

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

const openEditModal = (user: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = user.id;
    form.name = user.name;
    form.email = user.email;
    form.password = ''; // leave blank if no change
    form.phone_number = user.phone_number;
    form.role = user.role;
    form.department_id = user.department_id;
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
    <Head title="Manajemen Pengguna" />

    <AuthenticatedLayout>
        <template #header>
            Manajemen Akun Pengguna
        </template>

        <div class="space-y-6">
            <DataTable 
                :columns="columns" 
                :data="users"
                v-model="searchQuery"
                @update:modelValue="applyFilters"
                @sort="handleSort"
                @page="handlePage"
                searchPlaceholder="Cari nama, email, WA..."
            >
                <template #filters>
                    <div class="w-48">
                        <Select v-model="roleFilter" @update:modelValue="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="Semua Role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua Role</SelectItem>
                                <SelectItem value="admin">Admin</SelectItem>
                                <SelectItem value="technician">Teknisi</SelectItem>
                                <SelectItem value="opd_user">Perwakilan OPD</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </template>

                <template #actions>
                    <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                        <Plus class="w-4 h-4 mr-2" /> Tambah User
                    </Button>
                </template>

                <template #cell-name="{ item }">
                    <div class="font-medium text-slate-900">{{ item.name }}</div>
                    <div class="text-xs text-slate-500">{{ item.email }}</div>
                    <div class="text-xs text-slate-500">{{ item.phone_number }}</div>
                </template>

                <template #cell-role="{ item }">
                    <Badge v-if="item.role === 'admin'" class="bg-purple-100 text-purple-800 border-transparent hover:bg-purple-200">Admin</Badge>
                    <Badge v-else-if="item.role === 'technician'" class="bg-sky-100 text-sky-800 border-transparent hover:bg-sky-200">Teknisi</Badge>
                    <Badge v-else class="bg-slate-100 text-slate-800 border-transparent hover:bg-slate-200">OPD</Badge>
                </template>
                
                <template #cell-department="{ item }">
                    <span v-if="item.department" class="text-sm">{{ item.department.name }}</span>
                    <span v-else class="text-xs text-slate-400 italic">Bukan user OPD</span>
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge :status="item.status" />
                </template>

                <template #actions-cell="{ item }">
                    <div class="flex items-center space-x-2" v-if="item.id !== $page.props.auth.user.id">
                        <Button variant="ghost" size="icon" @click="openEditModal(item)">
                            <Edit2 class="w-4 h-4 text-slate-500" />
                        </Button>
                        <Button variant="ghost" size="icon" @click="openDeleteDialog(item.id)">
                            <Trash2 class="w-4 h-4 text-red-500" />
                        </Button>
                    </div>
                    <span v-else class="text-xs text-slate-400 italic px-2">(Anda)</span>
                </template>
            </DataTable>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="w-[95vw] sm:max-w-[620px]">
                <DialogHeader class="pb-1">
                    <DialogTitle class="text-lg font-semibold text-slate-900">{{ isEditMode ? 'Edit Pengguna' : 'Tambah Pengguna Baru' }}</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Konfigurasi hak akses login dan penempatan instansi.
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
                            <InputLabel for="phone_number" value="Nomor WhatsApp" class="text-xs" />
                            <Input id="phone_number" v-model="form.phone_number" placeholder="Cth: 081234567890" class="h-9 text-sm" />
                            <InputError :message="form.errors.phone_number" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="password" :value="isEditMode ? 'Password Baru (Opsional)' : 'Password'" class="text-xs" />
                            <Input id="password" type="password" v-model="form.password" placeholder="Minimal 8 karakter" class="h-9 text-sm" />
                            <InputError :message="form.errors.password" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="role" value="Role Pengguna" class="text-xs" />
                            <Select v-model="form.role">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="admin">Admin Kominfo</SelectItem>
                                    <SelectItem value="technician">Teknisi Jaringan</SelectItem>
                                    <SelectItem value="opd_user">Perwakilan OPD</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.role" />
                        </div>
                        
                        <div class="space-y-1">
                            <InputLabel for="status" value="Status" class="text-xs" />
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

                        <div v-if="isOpdUser" class="col-span-1 sm:col-span-2 space-y-1">
                            <InputLabel for="department_id" value="Instansi / OPD Penempatan" class="text-xs" />
                            <Select v-model="form.department_id">
                                <SelectTrigger class="h-9 text-sm">
                                    <SelectValue placeholder="Pilih OPD Instansi..." />
                                </SelectTrigger>
                                <SelectContent class="max-h-52">
                                    <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
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
                            {{ isEditMode ? 'Simpan' : 'Daftarkan User' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus User</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus pengguna ini secara permanen?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button variant="destructive" @click="confirmDelete">Ya, Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>