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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    departments?: Array<{id: number, name: string}>;
    technicians?: Array<{id: number, name: string, phone_number?: string}>;
    isAdmin: boolean;
}>();

const form = useForm({
    title: '',
    location_details: '',
    description: '',
    attachments: [] as File[],
    // Admin On-Behalf Fields
    department_id: '',
    network_type: '',
    category_id: '',
    priority: 'medium',
    technician_ids: [] as number[],
});

const availableCategories = computed(() => {
    if (!form.network_type || !props.categoriesMap) return [];
    return props.categoriesMap[form.network_type] || [];
});

const handleNetworkChange = (type: string) => {
    form.network_type = type;
    form.category_id = '';
};

const toggleTechnician = (techId: number) => {
    const index = form.technician_ids.indexOf(techId);
    if (index === -1) {
        form.technician_ids.push(techId);
    } else {
        form.technician_ids.splice(index, 1);
    }
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
                <span>{{ isAdmin ? 'Buat Tiket Darurat (On-Behalf)' : 'Buat Laporan Gangguan Baru' }}</span>
                <Link :href="route('tickets.index')">
                    <Button variant="outline" size="sm">Kembali ke Antrean</Button>
                </Link>
            </div>
        </template>

        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- OPD Flow Explanatory Banner -->
            <div v-if="!isAdmin" class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3 shadow-xs">
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
                    <CardTitle>{{ isAdmin ? 'Formulir Tiket On-Behalf Diskominfo' : 'Formulir Pengaduan Gangguan OPD' }}</CardTitle>
                    <CardDescription>
                        {{ isAdmin 
                            ? 'Isi formulir secara lengkap termasuk estimasi teknis dan penugasan tim teknisi.' 
                            : 'Isi detail gangguan di ruangan kerja Anda agar dapat segera diverifikasi oleh tim Diskominfo.' 
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        
                        <!-- ================= ADMIN ON-BEHALF SECTION ================= -->
                        <div v-if="isAdmin" class="p-5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-5">
                            <div class="flex items-center gap-2 border-b border-amber-200 pb-3">
                                <ShieldAlert class="w-5 h-5 text-amber-700" />
                                <div>
                                    <h3 class="font-semibold text-amber-950 text-sm">Pengaturan Tiket On-Behalf (Admin)</h3>
                                    <p class="text-xs text-amber-700">Tiket akan langsung berstatus In Progress dan mengaktifkan perhitungan SLA.</p>
                                </div>
                            </div>

                            <!-- 1. Instansi Pelapor -->
                            <div>
                                <InputLabel for="department_id" value="Pilih Instansi / OPD Pelapor *" class="text-amber-950 font-medium" />
                                <Select v-model="form.department_id">
                                    <SelectTrigger class="bg-white mt-1.5">
                                        <SelectValue placeholder="Pilih Instansi OPD..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="dept in departments" :key="dept.id" :value="dept.id.toString()">
                                            {{ dept.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.department_id" class="mt-1" />
                            </div>

                            <!-- 2. Jenis Jaringan & Kategori -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel value="Jenis Infrastruktur Jaringan *" class="text-amber-950 font-medium mb-1.5" />
                                    <div class="grid grid-cols-3 gap-2">
                                        <button 
                                            type="button"
                                            @click="handleNetworkChange('fiber_optic')"
                                            class="border rounded-lg p-2.5 text-center flex flex-col items-center justify-center gap-1.5 transition-all text-xs font-medium"
                                            :class="form.network_type === 'fiber_optic' ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'"
                                        >
                                            <Cable class="h-5 w-5 text-purple-600" />
                                            <span>Fiber Optic</span>
                                        </button>
                                        <button 
                                            type="button"
                                            @click="handleNetworkChange('lan')"
                                            class="border rounded-lg p-2.5 text-center flex flex-col items-center justify-center gap-1.5 transition-all text-xs font-medium"
                                            :class="form.network_type === 'lan' ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'"
                                        >
                                            <Network class="h-5 w-5 text-cyan-600" />
                                            <span>LAN</span>
                                        </button>
                                        <button 
                                            type="button"
                                            @click="handleNetworkChange('wifi')"
                                            class="border rounded-lg p-2.5 text-center flex flex-col items-center justify-center gap-1.5 transition-all text-xs font-medium"
                                            :class="form.network_type === 'wifi' ? 'border-amber-600 bg-white ring-2 ring-amber-500 text-amber-900 shadow-xs' : 'border-amber-200 bg-amber-50/50 hover:bg-white text-slate-700'"
                                        >
                                            <Wifi class="h-5 w-5 text-sky-600" />
                                            <span>WiFi</span>
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.network_type" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="category_id" value="Kategori Masalah / Dugaan Awal *" class="text-amber-950 font-medium mb-1.5" />
                                    <Select v-model="form.category_id" :disabled="!form.network_type">
                                        <SelectTrigger class="bg-white">
                                            <SelectValue :placeholder="form.network_type ? 'Pilih kategori...' : 'Pilih jenis jaringan dahulu'" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="cat in availableCategories" :key="cat.id" :value="cat.id.toString()">
                                                {{ cat.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.category_id" class="mt-1" />
                                </div>
                            </div>

                            <!-- 3. Prioritas & Multi-select Tim Teknisi -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <InputLabel for="priority" value="Tingkat Prioritas Penanganan *" class="text-amber-950 font-medium" />
                                    <Select v-model="form.priority">
                                        <SelectTrigger class="bg-white mt-1.5">
                                            <SelectValue placeholder="Pilih prioritas" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="low">Rendah</SelectItem>
                                            <SelectItem value="medium">Sedang</SelectItem>
                                            <SelectItem value="high">Tinggi</SelectItem>
                                            <SelectItem value="emergency">Darurat (Emergency)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="form.errors.priority" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel value="Tim Teknisi Penanggung Jawab *" class="text-amber-950 font-medium" />
                                    <div class="mt-1.5 bg-white border border-amber-200 rounded-lg p-3 max-h-40 overflow-y-auto space-y-2">
                                        <div v-if="!technicians || technicians.length === 0" class="text-xs text-slate-500 italic">
                                            Tidak ada teknisi aktif tersedia.
                                        </div>
                                        <label 
                                            v-for="tech in technicians" 
                                            :key="tech.id"
                                            class="flex items-center gap-2.5 text-xs text-slate-800 cursor-pointer hover:bg-slate-50 p-1.5 rounded-sm transition-colors"
                                        >
                                            <input 
                                                type="checkbox" 
                                                :checked="form.technician_ids.includes(tech.id)"
                                                @change="toggleTechnician(tech.id)"
                                                class="rounded border-slate-300 text-kominfo-primary focus:ring-0 focus:ring-offset-0 focus:outline-none outline-none w-4 h-4 cursor-pointer"
                                            />
                                            <span class="font-medium">{{ tech.name }}</span>
                                            <span v-if="tech.phone_number" class="text-slate-400 font-mono text-[10px]">({{ tech.phone_number }})</span>
                                        </label>
                                    </div>
                                    <p class="text-[11px] text-amber-800 mt-1">Dapat memilih lebih dari 1 teknisi (penugasan tim).</p>
                                    <InputError :message="form.errors.technician_ids" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- ================= COMMON FIELDS SECTION (OPD & ADMIN) ================= -->
                        
                        <!-- 1. Subjek & Lokasi Ruangan -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <InputLabel for="title" value="Subjek / Ringkasan Kendala *" />
                                <Input 
                                    id="title" 
                                    v-model="form.title" 
                                    placeholder="Cth: Internet mati di ruang rapat atau WiFi tidak bisa login"
                                    class="mt-1.5"
                                />
                                <InputError :message="form.errors.title" class="mt-1" />
                            </div>
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
                        </div>

                        <!-- 2. Deskripsi Kendala -->
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

                        <!-- 3. Lampiran Bukti Foto -->
                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel value="Foto Bukti Awal Kendala" />
                                <span class="text-xs text-slate-400 font-normal italic">(Opsional - Maks. 3 Gambar)</span>
                            </div>
                            <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto perangkat (modem, switch, access point) atau tangkapan layar pesan error jika ada.</p>
                            <FileUpload 
                                v-model="form.attachments" 
                                :multiple="true" 
                                :max-files="3" 
                                :max-size-m-b="5"
                                @error="(msg) => form.errors.attachments = msg"
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
                                <span v-else>{{ isAdmin ? 'Terbitkan Tiket & Tugaskan Tim' : 'Kirim Laporan Gangguan' }}</span>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
