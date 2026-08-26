<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FileUpload from '@/Components/FileUpload.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Cable, Network, Wifi } from 'lucide-vue-next';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    categoriesMap: Record<string, Array<{id: number, name: string, network_type: string}>>;
    departments: Array<{id: number, name: string}>;
    isAdmin: boolean;
}>();

const form = useForm({
    network_type: '',
    category_id: '',
    title: '',
    location_details: '',
    description: '',
    priority: 'medium',
    department_id: '',
    attachments: [] as File[],
});

const availableCategories = computed(() => {
    if (!form.network_type) return [];
    return props.categoriesMap[form.network_type] || [];
});

const handleNetworkChange = (type: string) => {
    form.network_type = type;
    form.category_id = ''; // reset category when network type changes
};

const submitForm = () => {
    form.post(route('tickets.store'));
};
</script>

<template>
    <Head title="Buat Tiket Baru" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>Buat Laporan Gangguan Baru</span>
                <Link :href="route('tickets.index')">
                    <Button variant="outline" size="sm">Kembali ke Antrean</Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Formulir Pengaduan Jaringan</CardTitle>
                    <CardDescription>Isi detail gangguan yang Anda alami agar tim teknis dapat segera menanganinya.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        
                        <!-- On Behalf (Admin Only) -->
                        <div v-if="isAdmin" class="p-4 bg-amber-50 border border-amber-200 rounded-lg space-y-3">
                            <div>
                                <h3 class="font-medium text-amber-900 flex items-center gap-2">
                                    Pembuatan Tiket Darurat (On-Behalf)
                                </h3>
                                <p class="text-xs text-amber-700">Pilih instansi pelapor karena Anda bertindak sebagai Admin.</p>
                            </div>
                            <div>
                                <InputLabel for="department_id" value="Pilih Instansi / OPD Pelapor" class="text-amber-900" />
                                <Select v-model="form.department_id">
                                    <SelectTrigger class="bg-white">
                                        <SelectValue placeholder="Pilih OPD..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
                                            {{ dept.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.department_id" />
                            </div>
                        </div>

                        <!-- 1. Pilihan Jaringan (Radio Cards) -->
                        <div>
                            <InputLabel value="Jenis Infrastruktur Gangguan" />
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                                <div 
                                    @click="handleNetworkChange('fiber_optic')"
                                    class="border rounded-lg p-4 cursor-pointer transition-all text-center flex flex-col items-center justify-center gap-2"
                                    :class="form.network_type === 'fiber_optic' ? 'border-kominfo-primary bg-blue-50 ring-1 ring-kominfo-primary' : 'border-slate-200 hover:border-kominfo-accent hover:bg-slate-50'"
                                >
                                    <Cable class="h-8 w-8 text-purple-600" />
                                    <span class="font-medium text-slate-800">Fiber Optic</span>
                                </div>
                                <div 
                                    @click="handleNetworkChange('lan')"
                                    class="border rounded-lg p-4 cursor-pointer transition-all text-center flex flex-col items-center justify-center gap-2"
                                    :class="form.network_type === 'lan' ? 'border-kominfo-primary bg-blue-50 ring-1 ring-kominfo-primary' : 'border-slate-200 hover:border-kominfo-accent hover:bg-slate-50'"
                                >
                                    <Network class="h-8 w-8 text-cyan-600" />
                                    <span class="font-medium text-slate-800">Jaringan LAN</span>
                                </div>
                                <div 
                                    @click="handleNetworkChange('wifi')"
                                    class="border rounded-lg p-4 cursor-pointer transition-all text-center flex flex-col items-center justify-center gap-2"
                                    :class="form.network_type === 'wifi' ? 'border-kominfo-primary bg-blue-50 ring-1 ring-kominfo-primary' : 'border-slate-200 hover:border-kominfo-accent hover:bg-slate-50'"
                                >
                                    <Wifi class="h-8 w-8 text-sky-600" />
                                    <span class="font-medium text-slate-800">Jaringan WiFi</span>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="form.errors.network_type" />
                        </div>

                        <!-- 2. Kategori (Dinamis dari Pilihan Jaringan) -->
                        <div>
                            <InputLabel for="category_id" value="Kategori Masalah" />
                            <Select v-model="form.category_id" :disabled="!form.network_type">
                                <SelectTrigger>
                                    <SelectValue :placeholder="form.network_type ? 'Pilih kategori gangguan...' : 'Pilih jenis jaringan terlebih dahulu'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in availableCategories" :key="cat.id" :value="cat.id.toString()">
                                        {{ cat.name }}
                                    </SelectItem>
                                    <div v-if="availableCategories.length === 0 && form.network_type" class="p-2 text-sm text-slate-500">
                                        Tidak ada kategori aktif untuk jaringan ini.
                                    </div>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.category_id" />
                        </div>

                        <!-- 3. Subjek & Lokasi -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="title" value="Subjek Singkat" />
                                <Input id="title" v-model="form.title" placeholder="Cth: Internet ruang pelayanan mati" />
                                <InputError :message="form.errors.title" />
                            </div>
                            <div>
                                <InputLabel for="location_details" value="Lokasi / Ruangan Spesifik" />
                                <Input id="location_details" v-model="form.location_details" placeholder="Cth: Gedung A Lantai 2" />
                                <InputError :message="form.errors.location_details" />
                            </div>
                        </div>

                        <!-- 4. Deskripsi -->
                        <div>
                            <InputLabel for="description" value="Deskripsi Rinci Masalah" />
                            <Textarea 
                                id="description" 
                                v-model="form.description" 
                                rows="5" 
                                placeholder="Jelaskan secara detail kendala yang dialami, sejak kapan, dan dampaknya..."
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <!-- 5. Prioritas & Attachments -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <InputLabel for="priority" value="Tingkat Urgensi" />
                                <Select v-model="form.priority">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tingkat prioritas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Rendah</SelectItem>
                                        <SelectItem value="medium">Sedang</SelectItem>
                                        <SelectItem value="high">Tinggi</SelectItem>
                                        <SelectItem v-if="isAdmin" value="emergency">Darurat (Admin Only)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.priority" />
                                <p class="text-xs text-slate-500 mt-2">Gunakan prioritas Sedang untuk kendala umum.</p>
                            </div>

                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between">
                                    <InputLabel value="Lampiran Bukti Foto" />
                                    <span class="text-xs text-slate-400 font-normal italic">(Opsional / Tidak Wajib)</span>
                                </div>
                                <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto kendala atau perangkat jika ada untuk mempercepat proses identifikasi.</p>
                                <FileUpload 
                                    v-model="form.attachments" 
                                    :multiple="true" 
                                    :max-files="3" 
                                    :max-size-m-b="5"
                                    @error="(msg) => form.errors.attachments = msg"
                                />
                                <InputError :message="form.errors.attachments" class="mt-2" />
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-end pt-4 border-t border-slate-100">
                            <Button 
                                type="submit" 
                                class="bg-kominfo-primary hover:bg-kominfo-primary-dark min-w-[150px]"
                                :disabled="form.processing"
                            >
                                <span v-if="form.processing">Memproses...</span>
                                <span v-else>Kirim Laporan Tiket</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
