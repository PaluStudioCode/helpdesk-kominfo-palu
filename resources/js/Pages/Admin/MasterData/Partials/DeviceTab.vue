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
    devices: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'devices' ? (props.filters?.search || '') : '');

const columns = [
    { key: 'name', label: 'Nama Perangkat / Node', sortable: true },
    { key: 'code', label: 'Kode Singkatan', sortable: true },
    { key: 'description', label: 'Deskripsi / Peruntukan', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'devices',
        search: searchQuery.value,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'devices',
        search: searchQuery.value,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        dev_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    searchQuery.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'devices',
        search: value,
        dev_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const deviceToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    code: '',
    description: '',
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    form.status = 'active';
    isModalOpen.value = true;
};

const openEditModal = (device: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = device.id;
    form.name = device.name;
    form.code = device.code || '';
    form.description = device.description || '';
    form.status = device.status;
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value) {
        form.put(route('admin.devices.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.devices.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const confirmDelete = (id: number) => {
    deviceToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const deleteDevice = () => {
    if (deviceToDelete.value) {
        router.delete(route('admin.devices.destroy', deviceToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                deviceToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <div class="space-y-4">
        <DataTable
            :columns="columns"
            :data="devices"
            :modelValue="searchQuery"
            searchPlaceholder="Cari perangkat atau node..."
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
        >
            <template #actions>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah Perangkat / Node
                </Button>
            </template>

            <!-- Custom Cell Rendering (No badges, consistent typography) -->
            <template #cell-name="{ item }">
                <span class="font-medium text-slate-900">{{ item.name }}</span>
            </template>

            <template #cell-code="{ item }">
                <span v-if="item.code" class="font-mono text-xs text-slate-700">{{ item.code }}</span>
                <span v-else class="text-slate-400 italic">-</span>
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
                        title="Edit Perangkat"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus Perangkat"
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
                    <DialogTitle>{{ isEditMode ? 'Edit Perangkat / Node Jaringan' : 'Tambah Perangkat / Node Baru' }}</DialogTitle>
                    <DialogDescription>
                        Data ini akan muncul pada pilihan dropdown "Perangkat/Node Terdampak" saat teknisi menyelesaikan laporan perbaikan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 py-2">
                    <div>
                        <InputLabel for="name" value="Nama Perangkat / Node Jaringan *" />
                        <Input 
                            id="name" 
                            v-model="form.name" 
                            placeholder="Cth: Router / Gateway, Switch Access, Access Point Outdoor" 
                            class="mt-1" 
                            required 
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="code" value="Kode Singkatan (Opsional)" />
                        <Input 
                            id="code" 
                            v-model="form.code" 
                            placeholder="Cth: RTR, SW-ACC, AP-IN" 
                            class="mt-1" 
                        />
                        <InputError :message="form.errors.code" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="description" value="Deskripsi / Peruntukan (Opsional)" />
                        <Textarea 
                            id="description" 
                            v-model="form.description" 
                            placeholder="Keterangan singkat fungsi atau lokasi penempatan perangkat..." 
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
                            {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Perbarui Perangkat' : 'Simpan Perangkat') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle>Hapus Perangkat / Node</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin menghapus perangkat ini? Perangkat yang telah dihapus tidak akan muncul lagi di opsi teknisi.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4">
                    <Button type="button" variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" @click="deleteDevice">Hapus</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
