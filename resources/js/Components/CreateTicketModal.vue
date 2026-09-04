<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FileUpload from '@/Components/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
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
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const opdCreateForm = useForm({
    title: '',
    location_details: '',
    description: '',
    attachments: [] as File[],
});

// Common non-technical issue options for OPD
const opdIssueOptions = [
    { value: 'Internet Mati Total (Tidak Ada Koneksi)', label: 'Internet Mati Total (Tidak Ada Koneksi)' },
    { value: 'Koneksi Internet Sangat Lambat / Putus-Nyambung', label: 'Koneksi Internet Sangat Lambat / Putus-Nyambung' },
    { value: 'WiFi Kantor Tidak Terdeteksi / Tidak Bisa Terhubung', label: 'WiFi Kantor Tidak Terdeteksi / Tidak Bisa Terhubung' },
    { value: 'Komputer Tertentu Tidak Bisa Akses Internet', label: 'Komputer Tertentu Tidak Bisa Akses Internet' },
    { value: 'Aplikasi / Website Pemerintah Daerah Tidak Bisa Dibuka', label: 'Aplikasi / Website Pemerintah Daerah Tidak Bisa Dibuka' },
    { value: 'Perangkat / Kabel Jaringan Rusak Fisik atau Terlepas', label: 'Perangkat / Kabel Jaringan Rusak Fisik atau Terlepas' },
    { value: 'other', label: 'Kendala Lainnya (Tuliskan Sendiri)' },
];

const selectedOpdIssueOption = ref<string>('');

const onSelectOpdIssueOption = (val: any) => {
    selectedOpdIssueOption.value = val;
    if (val === 'other') {
        opdCreateForm.title = '';
    } else {
        opdCreateForm.title = val;
    }
};

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        opdCreateForm.reset();
        opdCreateForm.clearErrors();
        selectedOpdIssueOption.value = '';
    }
});

const closeModal = () => {
    emit('update:open', false);
};

const submitCreateTicket = () => {
    opdCreateForm.post(route('tickets.store'), {
        onSuccess: () => {
            closeModal();
            opdCreateForm.reset();
            emit('success');
        }
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="w-full h-full max-w-full max-h-full rounded-none top-0 left-0 translate-x-0 translate-y-0 p-4 sm:p-6 overflow-y-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:max-w-[650px] sm:max-h-[90vh] sm:h-auto sm:rounded-xl">
            <DialogHeader class="pb-2 border-b border-slate-100 sm:border-none">
                <DialogTitle class="text-lg sm:text-xl font-bold text-slate-900">Buat Laporan Tiket Gangguan</DialogTitle>
                <DialogDescription class="text-xs sm:text-sm text-slate-500">
                    Lengkapi detail kendala jaringan kantor Anda untuk diteruskan ke tim teknisi Kominfo.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitCreateTicket" class="space-y-4 pt-1 sm:pt-2">
                <!-- Jenis Kendala / Subjek Laporan -->
                <div>
                    <InputLabel value="Jenis Kendala / Subjek Laporan *" class="text-xs font-semibold text-slate-700" />
                    <Select :modelValue="selectedOpdIssueOption" @update:modelValue="onSelectOpdIssueOption">
                        <SelectTrigger class="mt-1">
                            <SelectValue placeholder="-- Pilih Jenis Kendala yang Dialami --" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem 
                                v-for="opt in opdIssueOptions" 
                                :key="opt.value" 
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="opdCreateForm.errors.title" class="mt-1" />

                    <!-- Text input appears ONLY when 'other' is selected -->
                    <div v-if="selectedOpdIssueOption === 'other'" class="mt-2">
                        <Input 
                            id="opd_title" 
                            v-model="opdCreateForm.title" 
                            placeholder="Tuliskan ringkasan kendala yang dialami..." 
                            class="mt-1" 
                        />
                        <p class="text-[11px] text-slate-500 mt-1">Tuliskan ringkasan kendala secara singkat (minimal 5 karakter).</p>
                    </div>
                </div>

                <!-- Location Details -->
                <div>
                    <InputLabel for="opd_location_details" value="Lokasi Detail / Ruangan *" />
                    <Input id="opd_location_details" v-model="opdCreateForm.location_details" placeholder="Cth: Gedung B Lantai 2, Ruang Rapat" class="mt-1" />
                    <InputError :message="opdCreateForm.errors.location_details" class="mt-1" />
                </div>

                <!-- Description -->
                <div>
                    <InputLabel for="opd_description" value="Deskripsi Detail Kendala *" />
                    <Textarea 
                        id="opd_description" 
                        v-model="opdCreateForm.description" 
                        rows="3" 
                        placeholder="Jelaskan kendala apa yang dialami, sejak kapan, dan dampaknya..." 
                        class="mt-1"
                    />
                    <InputError :message="opdCreateForm.errors.description" class="mt-1" />
                </div>

                <!-- Attachments -->
                <div>
                    <div class="flex items-center justify-between">
                        <InputLabel value="Lampiran Bukti Foto" />
                        <span class="text-xs text-slate-400 font-normal italic">(Opsional - Maks. 3 File)</span>
                    </div>
                    <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto perangkat atau pesan error jika ada.</p>
                    <FileUpload 
                        v-model="opdCreateForm.attachments"
                        :multiple="true"
                        :maxFiles="3"
                        :maxSizeMB="5"
                        @error="(msg) => opdCreateForm.errors.attachments = msg"
                    />
                    <InputError :message="opdCreateForm.errors.attachments" class="mt-1" />
                </div>

                <DialogFooter class="pt-3 pb-2 border-t border-slate-100 sticky bottom-0 bg-white sm:static">
                    <Button type="button" variant="outline" @click="closeModal">Batal</Button>
                    <Button type="submit" :disabled="opdCreateForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                        {{ opdCreateForm.processing ? 'Mengirim Laporan...' : 'Kirim Laporan Gangguan' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
