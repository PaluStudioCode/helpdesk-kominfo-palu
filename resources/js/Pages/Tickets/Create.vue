<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FileUpload from '@/Components/FileUpload.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Info } from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const form = useForm({
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
        form.title = '';
    } else {
        form.title = val;
    }
};

const submitForm = () => {
    form.post(route('tickets.store'));
};
</script>

<template>
    <Head title="Buat Laporan Gangguan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>Buat Laporan Gangguan Baru</span>
                <Link :href="route('tickets.index')">
                    <Button variant="outline" size="sm">Kembali ke Daftar Laporan</Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- OPD Flow Explanatory Banner -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3 shadow-xs">
                <Info class="w-5 h-5 text-blue-600 mt-0.5 shrink-0" />
                <div class="text-sm text-blue-800">
                    <p class="font-semibold text-blue-950">Alur Pelaporan Gangguan Mandiri</p>
                    <p class="text-blue-700 mt-0.5 leading-relaxed">
                        Anda cukup menjelaskan kendala yang dialami dengan bahasa sehari-hari dan lokasi spesifik. Tim Verifikator Diskominfo Kota Palu akan memvalidasi kelayakan laporan dan menugaskan tim teknisi jaringan ke lokasi Anda.
                    </p>
                </div>
            </div>

            <!-- Form Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Formulir Pengaduan Gangguan OPD</CardTitle>
                    <CardDescription>
                        Isi detail gangguan di ruangan kerja Anda agar dapat segera diverifikasi oleh tim Diskominfo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        
                        <!-- Jenis Kendala / Subjek Laporan (OPD Only) -->
                        <div>
                            <InputLabel value="Jenis Kendala / Subjek Laporan *" class="text-xs font-semibold text-slate-700" />
                            <Select :modelValue="selectedOpdIssueOption" @update:modelValue="onSelectOpdIssueOption">
                                <SelectTrigger class="bg-white mt-1.5">
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
                            <InputError :message="form.errors.title" class="mt-1" />

                            <!-- Text input appears ONLY when 'other' is selected -->
                            <div v-if="selectedOpdIssueOption === 'other'" class="mt-2">
                                <Input 
                                    id="title" 
                                    v-model="form.title" 
                                    placeholder="Tuliskan ringkasan kendala yang Anda alami..." 
                                    class="mt-1" 
                                />
                                <p class="text-[11px] text-slate-500 mt-1">Tuliskan ringkasan kendala secara singkat (minimal 5 karakter).</p>
                            </div>
                        </div>

                        <!-- Lokasi Ruangan -->
                        <div>
                            <InputLabel for="location_details" value="Lokasi Ruangan / Gedung Spesifik *" />
                            <Input 
                                id="location_details" 
                                v-model="form.location_details" 
                                placeholder="Cth: Gedung Timur Lantai 2, Ruang Bidang Informasi"
                                class="mt-1.5"
                            />
                            <InputError :message="form.errors.location_details" class="mt-1" />
                        </div>

                        <!-- Deskripsi Kendala -->
                        <div>
                            <InputLabel for="description" value="Deskripsi Detail Kendala / Gejala Awam *" />
                            <Textarea 
                                id="description" 
                                v-model="form.description" 
                                rows="4" 
                                placeholder="Jelaskan kendala apa yang terjadi (misal: lampu router mati, koneksi lambat, hanya beberapa komputer terdampak), sejak kapan, dan dampaknya..."
                                class="mt-1.5"
                            />
                            <InputError :message="form.errors.description" class="mt-1" />
                        </div>

                        <!-- Lampiran Bukti Foto -->
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel value="Foto Bukti Awal Kendala" />
                                <span class="text-xs text-slate-400 font-normal italic">(Opsional - Maks. 3 Gambar)</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto perangkat (modem, switch, access point) atau tangkapan layar pesan error jika ada.</p>
                            <FileUpload 
                                v-model="form.attachments" 
                                :multiple="true" 
                                :maxFiles="3" 
                                :maxSizeMB="5"
                                @error="(msg: string) => form.errors.attachments = msg"
                            />
                            <InputError :message="form.errors.attachments" class="mt-2" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end pt-4 border-t border-slate-100">
                            <Button 
                                type="submit" 
                                class="bg-kominfo-primary hover:bg-kominfo-primary-dark min-w-[160px]"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Mengirim Data...</span>
                                <span v-else>Kirim Laporan Gangguan</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
