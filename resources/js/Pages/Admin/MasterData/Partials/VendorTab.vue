<script setup lang="ts">
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import DataTable from '@/Components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Plus, Edit2, Trash2, Phone } from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
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
    vendors: any;
    filters: any;
}>();

const searchQuery = ref(props.filters?.tab === 'vendors' ? (props.filters?.search || '') : '');
const selectedCategory = ref(props.filters?.tab === 'vendors' ? (props.filters?.vendor_category || 'all') : 'all');

const vendorCategories = [
    'Penyedia Jaringan / ISP',
    'Kelistrikan & Daya',
    'Perangkat Keras & Jaringan',
    'Infrastruktur Kabel & Pasif',
    'Layanan Cloud & Aplikasi',
    'Lainnya',
];

const columns = [
    { key: 'name', label: 'Nama Mitra / Rekanan', sortable: true },
    { key: 'category', label: 'Kategori Bidang', sortable: true },
    { key: 'phone', label: 'No. Telepon', sortable: false },
    { key: 'address', label: 'Alamat / Kantor', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
];

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('admin.master-data.index'), {
        tab: 'vendors',
        search: searchQuery.value,
        vendor_category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('admin.master-data.index'), {
        tab: 'vendors',
        search: searchQuery.value,
        vendor_category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        sort: props.filters?.sort,
        direction: props.filters?.direction,
        ven_page: page
    }, { preserveState: true });
};

const handleSearch = (value: string) => {
    searchQuery.value = value;
    router.get(route('admin.master-data.index'), {
        tab: 'vendors',
        search: value,
        vendor_category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
        ven_page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleCategoryFilter = (val: string) => {
    selectedCategory.value = val;
    router.get(route('admin.master-data.index'), {
        tab: 'vendors',
        search: searchQuery.value,
        vendor_category: val !== 'all' ? val : undefined,
        ven_page: 1
    }, { preserveState: true, preserveScroll: true });
};

// Modal State
const isModalOpen = ref(false);
const isEditMode = ref(false);
const isDeleteDialogOpen = ref(false);
const vendorToDelete = ref<number | null>(null);

const form = useForm({
    id: null as number | null,
    name: '',
    category: 'Penyedia Jaringan / ISP',
    phone: '',
    address: '',
    description: '',
    status: 'active'
});

const openCreateModal = () => {
    isEditMode.value = false;
    form.reset();
    form.clearErrors();
    form.category = 'Penyedia Jaringan / ISP';
    form.status = 'active';
    isModalOpen.value = true;
};

const openEditModal = (vendor: any) => {
    isEditMode.value = true;
    form.reset();
    form.clearErrors();
    form.id = vendor.id;
    form.name = vendor.name;
    form.category = vendor.category || 'Penyedia Jaringan / ISP';
    form.phone = vendor.phone || '';
    form.address = vendor.address || '';
    form.description = vendor.description || '';
    form.status = vendor.status || 'active';
    isModalOpen.value = true;
};

const submitForm = () => {
    if (isEditMode.value && form.id) {
        form.put(route('admin.vendors.update', form.id), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    } else {
        form.post(route('admin.vendors.store'), {
            onSuccess: () => {
                isModalOpen.value = false;
            }
        });
    }
};

const confirmDelete = (id: number) => {
    vendorToDelete.value = id;
    isDeleteDialogOpen.value = true;
};

const deleteVendor = () => {
    if (vendorToDelete.value) {
        router.delete(route('admin.vendors.destroy', vendorToDelete.value), {
            onSuccess: () => {
                isDeleteDialogOpen.value = false;
                vendorToDelete.value = null;
            }
        });
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- Filter Bar -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <Select :model-value="selectedCategory" @update:model-value="handleCategoryFilter">
                    <SelectTrigger class="w-full sm:w-[240px] bg-white text-xs sm:text-sm">
                        <SelectValue placeholder="Semua Kategori Bidang" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Semua Kategori Bidang</SelectItem>
                        <SelectItem v-for="cat in vendorCategories" :key="cat" :value="cat">
                            {{ cat }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>
                <Button @click="openCreateModal" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium w-full sm:w-auto">
                    <Plus class="w-4 h-4 mr-1.5" /> Tambah Mitra / Rekanan
                </Button>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="vendors"
            :modelValue="searchQuery"
            searchPlaceholder="Cari nama mitra atau nomor telepon/kontak..."
            @update:modelValue="handleSearch"
            @sort="handleSort"
            @page="handlePage"
        >
            <!-- Custom Cell Rendering -->
            <template #cell-name="{ item }">
                <div>
                    <div class="font-medium text-slate-900">{{ item.name }}</div>
                    <div v-if="item.description" class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">
                        {{ item.description }}
                    </div>
                </div>
            </template>

            <template #cell-category="{ item }">
                <span class="text-xs sm:text-sm text-slate-700">
                    {{ item.category || '-' }}
                </span>
            </template>

            <template #cell-phone="{ item }">
                <div class="flex items-center gap-1.5 text-xs sm:text-sm text-slate-700">
                    <Phone class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span>{{ item.phone }}</span>
                </div>
            </template>

            <template #cell-address="{ item }">
                <span class="text-xs sm:text-sm text-slate-600 line-clamp-2">{{ item.address || '-' }}</span>
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
                        title="Edit Mitra"
                        @click="openEditModal(item)"
                    >
                        <Edit2 class="w-4 h-4" />
                    </Button>
                    <Button 
                        variant="ghost" 
                        size="icon" 
                        class="h-8 w-8 text-slate-500 hover:text-red-600 hover:bg-red-50" 
                        title="Hapus Mitra"
                        @click="confirmDelete(item.id)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <!-- Create / Edit Modal -->
        <Dialog v-model:open="isModalOpen">
            <DialogContent class="sm:max-w-[540px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditMode ? 'Edit Mitra / Rekanan Vendor' : 'Tambah Mitra / Rekanan Vendor Baru' }}</DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 py-1">
                    <div>
                        <InputLabel for="vendor_name" value="Nama Instansi / Perusahaan Rekanan *" />
                        <Input 
                            id="vendor_name" 
                            v-model="form.name" 
                            placeholder="Cth: PT Telkom Indonesia, PT Indonesia Comnets Plus (Icon+)" 
                            class="mt-1" 
                            required 
                        />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="vendor_category" value="Kategori Bidang Usaha *" />
                            <Select v-model="form.category">
                                <SelectTrigger id="vendor_category" class="mt-1 bg-white">
                                    <SelectValue placeholder="Pilih Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in vendorCategories" :key="cat" :value="cat">
                                        {{ cat }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="vendor_phone" value="No. Telepon *" />
                            <Input 
                                id="vendor_phone" 
                                v-model="form.phone" 
                                placeholder="Cth: 081234567890 / 0451-421147" 
                                class="mt-1" 
                                required 
                            />
                            <InputError :message="form.errors.phone" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="vendor_address" value="Alamat Kantor Operasional" />
                        <Input 
                            id="vendor_address" 
                            v-model="form.address" 
                            placeholder="Cth: Jl. Sam Ratulangi No. 1, Kota Palu" 
                            class="mt-1" 
                        />
                        <InputError :message="form.errors.address" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="vendor_description" value="Keterangan / Lingkup Kerjasama" />
                        <Textarea 
                            id="vendor_description" 
                            v-model="form.description" 
                            placeholder="Cth: Penyedia link internet FO utama dan backup astinet OPD" 
                            rows="2" 
                            class="mt-1 text-sm bg-white" 
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="vendor_status" value="Status Rekanan *" />
                        <Select v-model="form.status">
                            <SelectTrigger id="vendor_status" class="mt-1 bg-white">
                                <SelectValue placeholder="Pilih Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="active">Aktif (Tersedia pada formulir tiket)</SelectItem>
                                <SelectItem value="inactive">Nonaktif</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <Button type="button" variant="outline" @click="isModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white">
                            {{ form.processing ? 'Menyimpan...' : (isEditMode ? 'Simpan Perubahan' : 'Tambah Mitra') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Modal -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Hapus Mitra / Rekanan</DialogTitle>
                </DialogHeader>
                <div class="py-2 text-xs sm:text-sm text-slate-600">
                    Apakah Anda yakin ingin menghapus data mitra rekanan ini? Data yang telah dihapus tidak dapat dipulihkan.
                </div>
                <DialogFooter class="flex items-center justify-end gap-2">
                    <Button type="button" variant="outline" @click="isDeleteDialogOpen = false">Batal</Button>
                    <Button type="button" variant="destructive" @click="deleteVendor" class="bg-red-600 hover:bg-red-700 text-white">
                        Hapus Mitra
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
