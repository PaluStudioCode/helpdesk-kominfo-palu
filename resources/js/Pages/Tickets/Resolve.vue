<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import FileUpload from '@/Components/FileUpload.vue';
import { 
    getPriorityLabel, 
    getPriorityColor, 
    formatDateWithWita as formatDate 
} from '@/lib/ticket-helpers';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ArrowLeft,
    Plus,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps<{
    ticket: any;
    availableDevices?: string[];
    availableMaterials?: Array<{ name: string; default_unit: string }>;
}>();

// Default device options if not in DB
const defaultAffectedDeviceOptions = [
    'Router / Gateway',
    'Switch Core / Distribution',
    'Switch Access',
    'Access Point (AP) / WiFi Indoor',
    'Access Point (AP) / WiFi Outdoor',
    'Optical Termination Box (OTB) / Joint Closure',
    'Optical Distribution Point (ODP) / Splitter',
    'Media Converter / SFP Transceiver',
    'Kabel Fiber Optic (Drop Core / Feeder)',
    'Kabel UTP / Patch Cord / LAN RJ45',
    'Power Supply / PoE Injector / UPS',
    'Server / OLT / Rack Server',
];

const affectedDeviceOptions = computed(() => {
    return (props.availableDevices && props.availableDevices.length > 0)
        ? [...props.availableDevices]
        : [...defaultAffectedDeviceOptions];
});

// Default materials list if not in DB
const defaultMaterialList = [
    { name: 'Konektor RJ-45 Cat6', unit: 'pcs' },
    { name: 'Patch Cord UTP Cat6', unit: 'pcs' },
    { name: 'Kabel UTP / LAN Cat6', unit: 'meter' },
    { name: 'Kabel Drop Core Fiber Optic (1-Core / 2-Core)', unit: 'meter' },
    { name: 'Patch Cord Fiber Optic (SC-SC / LC-SC)', unit: 'pcs' },
    { name: 'Pigtail Fiber Optic SC/UPC', unit: 'pcs' },
    { name: 'Fast Connector SC/UPC', unit: 'pcs' },
    { name: 'Fast Connector SC/APC', unit: 'pcs' },
    { name: 'Protection Sleeve FO (Splicing)', unit: 'pcs' },
    { name: 'Optical Termination Box (OTB)', unit: 'unit' },
    { name: 'Optical Distribution Point (ODP)', unit: 'unit' },
    { name: 'Adaptor Fiber Optic SC/UPC', unit: 'pcs' },
    { name: 'SFP Transceiver Module (1.25G / 10G)', unit: 'pcs' },
    { name: 'Media Converter FO to LAN', unit: 'unit' },
    { name: 'PoE Injector (24V / 48V)', unit: 'pcs' },
    { name: 'Power Supply / Adaptor (12V / 24V)', unit: 'pcs' },
    { name: 'Access Point (AP)', unit: 'unit' },
    { name: 'Switch Hub (8-Port / 16-Port / 24-Port)', unit: 'unit' },
    { name: 'Router Board / Mikrotik', unit: 'unit' },
    { name: 'Stop Kontak / Steker Listrik', unit: 'pcs' },
    { name: 'Kabel Ties / Velcro', unit: 'pack' },
    { name: 'Pipa Conduit / Cable Protector Duct', unit: 'batang' },
    { name: 'Isolasi Listrik / Heat Shrink', unit: 'roll' },
];

const materialOptions = computed(() => {
    if (props.availableMaterials && props.availableMaterials.length > 0) {
        return props.availableMaterials.map((m: any, idx: number) => ({
            id: idx + 1,
            name: m.name,
            unit: m.default_unit || 'pcs',
        }));
    }
    return defaultMaterialList.map((m, idx) => ({
        id: idx + 1,
        name: m.name,
        unit: m.unit,
    }));
});

interface MaterialRow {
    material: string;
    quantity: number | null;
    unit: string;
    isCustom?: boolean;
}

const parseExistingMaterials = (str: string | null | undefined): MaterialRow[] => {
    if (!str || !str.trim()) {
        return [{ material: '', quantity: 1, unit: 'pcs', isCustom: false }];
    }
    
    const items = str.split(/,|\n/).map(s => s.trim()).filter(Boolean);
    const rows: MaterialRow[] = [];

    for (const item of items) {
        const matchWithParen = item.match(/^(.*?)\s*\(([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)\)$/);
        const matchWithColon = item.match(/^(.*?)\s*[:\-]\s*([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)$/);
        const matchLeadingQty = item.match(/^([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)\s+(.*)$/);
        const matchTrailingQty = item.match(/^(.*?)\s+([0-9]+(?:\.[0-9]+)?)\s*([a-zA-Z]+)$/);

        let rawName = '';
        let qty = 1;
        let unit = 'pcs';

        if (matchWithParen) {
            rawName = matchWithParen[1].trim();
            qty = parseFloat(matchWithParen[2]);
            unit = matchWithParen[3].toLowerCase();
        } else if (matchWithColon) {
            rawName = matchWithColon[1].trim();
            qty = parseFloat(matchWithColon[2]);
            unit = matchWithColon[3].toLowerCase();
        } else if (matchLeadingQty) {
            qty = parseFloat(matchLeadingQty[1]);
            unit = matchLeadingQty[2].toLowerCase();
            rawName = matchLeadingQty[3].trim();
        } else if (matchTrailingQty) {
            rawName = matchTrailingQty[1].trim();
            qty = parseFloat(matchTrailingQty[2]);
            unit = matchTrailingQty[3].toLowerCase();
        } else {
            rawName = item.trim();
            qty = 1;
            unit = 'pcs';
        }

        const matchedMat = materialOptions.value.find(m => m.name.toLowerCase() === rawName.toLowerCase());
        
        let finalUnit = unit;
        if (matchedMat) {
            finalUnit = matchedMat.unit;
            rows.push({
                material: matchedMat.name,
                quantity: isNaN(qty) ? 1 : qty,
                unit: finalUnit,
                isCustom: false,
            });
        } else {
            rows.push({
                material: rawName,
                quantity: isNaN(qty) ? 1 : qty,
                unit: finalUnit || 'pcs',
                isCustom: true,
            });
        }
    }

    return rows.length > 0 ? rows : [{ material: '', quantity: 1, unit: 'pcs', isCustom: false }];
};

// Initial state values
const initialDevice = props.ticket.affected_device && affectedDeviceOptions.value.includes(props.ticket.affected_device)
    ? props.ticket.affected_device
    : (affectedDeviceOptions.value[0] || '');

const selectedAffectedDevice = ref<string>(initialDevice);

const testResultOptions = [
    'Normal / Berfungsi Baik',
    'Koneksi Stabil (Latency Rendah)',
    'Perangkat Menyala Normal',
    'Temporary Bypass (Monitoring Lanjut)',
];

const selectedTestResult = ref<string>(
    props.ticket.test_result && testResultOptions.includes(props.ticket.test_result)
        ? props.ticket.test_result
        : 'Normal / Berfungsi Baik'
);

const materialRows = ref<MaterialRow[]>(parseExistingMaterials(props.ticket.materials_used));

const form = useForm({
    affected_device: initialDevice,
    actual_repair_location: props.ticket.actual_repair_location || props.ticket.location_details || '',
    infrastructure_type: props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic',
    network_type: props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic',
    inspection_result: props.ticket.inspection_result || '',
    root_cause: props.ticket.root_cause || '',
    action_taken: props.ticket.action_taken || '',
    materials_used: props.ticket.materials_used || '',
    test_result: selectedTestResult.value,
    test_parameters: props.ticket.test_parameters || '',
    notes: props.ticket.resolution_note || '',
    resolution_proofs: [] as File[],
});

const onSelectMaterial = (index: number, val: string) => {
    if (!materialRows.value[index]) return;
    if (val === '__custom__') {
        materialRows.value[index].isCustom = true;
        materialRows.value[index].material = '';
        materialRows.value[index].unit = 'pcs';
    } else {
        materialRows.value[index].isCustom = false;
        materialRows.value[index].material = val;
        const opt = materialOptions.value.find(m => m.name === val);
        if (opt) {
            materialRows.value[index].unit = opt.unit;
        } else {
            materialRows.value[index].unit = 'pcs';
        }
    }
    syncMaterialsUsed();
};

const syncMaterialsUsed = () => {
    const validRows = materialRows.value.filter(r => r.material && r.material.trim() !== '' && (r.quantity ?? 0) > 0);
    if (validRows.length > 0) {
        form.materials_used = validRows.map(r => {
            return `${r.material.trim()} (${r.quantity} ${r.unit})`;
        }).join(', ');
    } else {
        form.materials_used = '';
    }
};

const addMaterialRow = () => {
    materialRows.value.push({
        material: '',
        quantity: 1,
        unit: 'pcs',
        isCustom: false,
    });
};

const removeMaterialRow = (index: number) => {
    if (materialRows.value.length > 1) {
        materialRows.value.splice(index, 1);
    } else {
        materialRows.value[0] = {
            material: '',
            quantity: 1,
            unit: 'pcs',
            isCustom: false,
        };
    }
    syncMaterialsUsed();
};

watch(selectedAffectedDevice, (val) => {
    form.affected_device = val;
});

watch(selectedTestResult, (val) => {
    form.test_result = val;
});

watch(materialRows, () => {
    syncMaterialsUsed();
}, { deep: true });

const submitResolution = () => {
    form.affected_device = selectedAffectedDevice.value;
    form.test_result = selectedTestResult.value;
    syncMaterialsUsed();

    form.post(route('tickets.submit-resolution', props.ticket.id));
};
</script>

<template>
    <Head :title="`Berita Acara - ${ticket.ticket_number}`" />

    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto space-y-4 pb-12">
            
            <!-- Header Navigasi -->
            <div class="space-y-1">
                <Link 
                    :href="route('tickets.show', ticket.id)"
                    class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft class="w-3.5 h-3.5 mr-1.5" />
                    Kembali ke Tiket {{ ticket.ticket_number }}
                </Link>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                    Formulir Berita Acara & Penyelesaian Tiket
                </h1>
            </div>

            <!-- Single Unified Docket Card (Format Dokumen Formal, Proporsional, dan Rapi) -->
            <form @submit.prevent="submitResolution">
                <Card class="border-slate-200 shadow-xs bg-white rounded-xl overflow-hidden">
                    
                    <!-- Header Kartu Utama: Info Ringkas Tiket -->
                    <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/75 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="text-[11px] font-medium text-slate-500 block">Subjek Gangguan:</span>
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight leading-snug truncate">
                                {{ ticket.title }}
                            </h2>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 text-xs sm:text-sm">
                            <div>
                                <span class="text-slate-400">Nomor:</span>
                                <span class="font-mono font-bold text-slate-900 ml-1">{{ ticket.ticket_number }}</span>
                            </div>
                            <span class="text-slate-300">•</span>
                            <div>
                                <span class="text-slate-400">Prioritas:</span>
                                <span :class="['font-semibold ml-1', getPriorityColor(ticket.priority)]">
                                    {{ getPriorityLabel(ticket.priority) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <CardContent class="p-5 sm:p-6 space-y-5">
                        
                        <!-- Panel Ringkas Informasi Tiket Rujukan -->
                        <div class="p-3.5 bg-slate-50/80 rounded-lg border border-slate-200">
                            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                                <div>
                                    <dt class="text-slate-500 font-medium">Instansi Pelapor</dt>
                                    <dd class="text-slate-900 font-semibold mt-0.5 text-xs sm:text-sm truncate">{{ ticket.department?.name || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 font-medium">Nama Pelapor</dt>
                                    <dd class="text-slate-900 font-semibold mt-0.5 text-xs sm:text-sm truncate">
                                        {{ ticket.reporter?.name || ticket.user?.name || '-' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 font-medium">Lokasi / Ruangan</dt>
                                    <dd class="text-slate-800 mt-0.5 text-xs sm:text-sm truncate">{{ ticket.location_details || '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500 font-medium">Batas Waktu (SLA)</dt>
                                    <dd class="text-slate-800 font-semibold mt-0.5 text-xs sm:text-sm">{{ ticket.due_at ? formatDate(ticket.due_at) : '-' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Baris 1: Identifikasi Perangkat, Lokasi Riil & Jenis Infrastruktur (3 Kolom Seimbang) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <InputLabel value="Perangkat / Komponen Terdampak *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Select v-model="selectedAffectedDevice">
                                    <SelectTrigger class="bg-white h-9 text-xs">
                                        <SelectValue placeholder="Pilih Perangkat / Komponen" />
                                    </SelectTrigger>
                                    <SelectContent class="max-h-56">
                                        <SelectItem v-for="dev in affectedDeviceOptions" :key="dev" :value="dev">
                                            {{ dev }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.affected_device" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="actual_repair_location" value="Titik Lokasi Riil Perbaikan *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Input 
                                    id="actual_repair_location"
                                    type="text" 
                                    v-model="form.actual_repair_location" 
                                    placeholder="Cth: Ruang Server Lt. 1 / Tiang ODP-04"
                                    class="bg-white text-xs h-9"
                                    required
                                />
                                <InputError :message="form.errors.actual_repair_location" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel value="Jenis Infrastruktur Riil *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Select 
                                    :modelValue="form.infrastructure_type"
                                    @update:modelValue="(val: any) => {
                                        form.infrastructure_type = val;
                                        form.network_type = val;
                                    }"
                                >
                                    <SelectTrigger class="bg-white h-9 text-xs">
                                        <SelectValue placeholder="Pilih Jenis Infrastruktur" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Fiber optic">Fiber optic (Dropcore, OTB, Joint)</SelectItem>
                                        <SelectItem value="Perangkat/Akses">Perangkat/Akses (Switch, Router, AP)</SelectItem>
                                        <SelectItem value="Power/poe">Power/poe (Adaptor, UPS, Power)</SelectItem>
                                        <SelectItem value="Converter">Converter (Media Converter, SFP)</SelectItem>
                                        <SelectItem value="Layanan/jaringan">Layanan/jaringan (IP, DHCP, Internet)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.infrastructure_type" class="mt-1" />
                            </div>
                        </div>

                        <!-- Baris 2: Hasil Pemeriksaan Lapangan & Akar Masalah (2 Kolom Sejajar) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="inspection_result" value="Hasil Pemeriksaan Awal (Kondisi Lapangan) *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Textarea 
                                    id="inspection_result" 
                                    v-model="form.inspection_result" 
                                    rows="3" 
                                    placeholder="Cth: Redaman sinyal FO terukur -32.5 dBm. Lampu indikator LOS menyala merah pada modem..."
                                    class="bg-white text-xs"
                                    required
                                />
                                <InputError :message="form.errors.inspection_result" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="root_cause" value="Penyebab Utama Gangguan (Root Cause) *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Textarea 
                                    id="root_cause" 
                                    v-model="form.root_cause" 
                                    rows="3" 
                                    placeholder="Cth: Kabel dropcore tertekuk dan terjepit rangka plafon saat pengerjaan instalasi..."
                                    class="bg-white text-xs"
                                    required
                                />
                                <InputError :message="form.errors.root_cause" class="mt-1" />
                            </div>
                        </div>

                        <!-- Baris 3: Rincian Tindakan Penanganan -->
                        <div>
                            <InputLabel for="action_taken" value="Rincian Tindakan Penanganan / Perbaikan *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                            <Textarea 
                                id="action_taken" 
                                v-model="form.action_taken" 
                                rows="3" 
                                placeholder="Contoh:&#10;1. Memotong bagian kabel dropcore yang rusak dan menarik ulang 15 meter.&#10;2. Splicing core FO dan pemasangan protection sleeve.&#10;3. Pengujian redaman kembali dan verifikasi koneksi..."
                                class="bg-white text-xs font-mono"
                                required
                            />
                            <InputError :message="form.errors.action_taken" class="mt-1" />
                        </div>

                        <!-- Baris 4: Status Hasil Pengujian & Parameter (2 Kolom Seimbang) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Status Hasil Pengujian *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Select v-model="selectedTestResult">
                                    <SelectTrigger class="bg-white h-9 text-xs">
                                        <SelectValue placeholder="Pilih Hasil Pengujian Akhir" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="res in testResultOptions" :key="res" :value="res">
                                            {{ res }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.test_result" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="test_parameters" value="Parameter Pengujian (Nilai Riil) *" class="text-xs font-semibold text-slate-700 block mb-1.5" />
                                <Textarea 
                                    id="test_parameters" 
                                    v-model="form.test_parameters" 
                                    rows="3"
                                    placeholder="Cth: OPM -18.2 dBm / Ping 8.8.8.8 2ms (0% Loss) / Speedtest: Download 50 Mbps, Upload 20 Mbps..."
                                    class="bg-white text-xs font-mono"
                                    required
                                />
                                <InputError :message="form.errors.test_parameters" class="mt-1" />
                            </div>
                        </div>

                        <!-- Baris 5: Material & Suku Cadang yang Digunakan -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <InputLabel value="Material & Suku Cadang yang Digunakan" class="text-xs font-semibold text-slate-700" />
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm" 
                                    @click="addMaterialRow"
                                    class="text-xs h-7 px-2.5 border-slate-300 bg-white hover:bg-slate-50"
                                >
                                    <Plus class="w-3.5 h-3.5 mr-1" />
                                    Tambah Barang
                                </Button>
                            </div>
                            
                            <div v-if="materialRows.length > 0" class="space-y-2">
                                <div 
                                    v-for="(item, index) in materialRows" 
                                    :key="index"
                                    class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 p-2.5 bg-slate-50/75 rounded-lg border border-slate-200"
                                >
                                    <!-- Nama Material Select / Custom -->
                                    <div class="flex-1">
                                        <Select 
                                            :modelValue="item.isCustom ? '__custom__' : item.material" 
                                            @update:modelValue="(val: string) => onSelectMaterial(index, val)"
                                        >
                                            <SelectTrigger class="h-9 text-xs bg-white">
                                                <SelectValue placeholder="Pilih Material / Suku Cadang" />
                                            </SelectTrigger>
                                            <SelectContent class="max-h-56">
                                                <SelectItem v-for="mat in materialOptions" :key="mat.name" :value="mat.name">
                                                    {{ mat.name }} ({{ mat.unit }})
                                                </SelectItem>
                                                <SelectItem value="__custom__" class="font-semibold text-kominfo-primary border-t border-slate-100">
                                                    + Input Material Lainnya...
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <!-- Custom Material Name Input -->
                                        <Input 
                                            v-if="item.isCustom"
                                            type="text"
                                            v-model="item.material"
                                            @input="syncMaterialsUsed"
                                            placeholder="Ketik nama spesifik material / barang..."
                                            class="h-8 text-xs mt-1.5 bg-white"
                                            required
                                        />
                                    </div>

                                    <!-- Jumlah / Qty -->
                                    <div class="w-full sm:w-28">
                                        <Input 
                                            type="number" 
                                            v-model.number="item.quantity" 
                                            @input="syncMaterialsUsed"
                                            min="0.1" 
                                            step="any"
                                            placeholder="Jumlah"
                                            class="h-9 text-xs bg-white"
                                            required
                                        />
                                    </div>

                                    <!-- Satuan / Unit (Terkunci / Non-Editable) -->
                                    <div class="w-full sm:w-28">
                                        <div class="h-9 px-3 flex items-center justify-center bg-slate-100 text-slate-700 border border-slate-200 rounded-md text-xs font-medium select-none cursor-not-allowed">
                                            {{ item.unit || '-' }}
                                        </div>
                                    </div>

                                    <!-- Hapus Row -->
                                    <div class="sm:pt-0">
                                        <Button 
                                            type="button" 
                                            variant="ghost" 
                                            size="sm" 
                                            @click="removeMaterialRow(index)"
                                            class="h-9 w-9 p-0 text-slate-400 hover:text-rose-600 hover:bg-rose-50 w-full sm:w-9"
                                            title="Hapus baris barang"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                            <span class="sm:hidden ml-1 text-xs text-rose-600">Hapus Barang</span>
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-xs text-slate-500 italic text-center py-2.5 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                                Tidak ada material atau suku cadang yang digunakan dalam perbaikan ini.
                            </div>
                        </div>

                        <!-- Baris 6: Dokumentasi Foto Hasil Perbaikan -->
                        <div class="space-y-1.5">
                            <InputLabel value="Dokumentasi Foto Hasil Perbaikan" class="text-xs font-semibold text-slate-700 block mb-1" />
                            <FileUpload 
                                v-model="form.resolution_proofs"
                                :multiple="true"
                                :maxFiles="3"
                                :maxSizeMB="5"
                                @error="(msg) => form.errors.resolution_proofs = msg"
                            />
                            <p class="text-[11px] text-slate-500">Unggah foto bukti pekerjaan di lokasi (maks. 3 file foto, JPG/PNG, maks. 5MB per file).</p>
                            <InputError :message="form.errors.resolution_proofs" class="mt-1" />
                        </div>

                    </CardContent>

                    <!-- Satu-Satunya Action Button di Paling Bawah Form -->
                    <div class="px-5 py-4 sm:px-6 sm:py-4 border-t border-slate-200 bg-slate-50/75 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <span class="text-xs text-slate-500">
                            Pastikan data berita acara terisi lengkap sebelum dikirim ke Admin Diskominfo.
                        </span>
                        <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                            <Link :href="route('tickets.show', ticket.id)" class="w-full sm:w-auto">
                                <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto">
                                    Batal
                                </Button>
                            </Link>
                            <Button 
                                type="submit" 
                                :disabled="form.processing || !form.action_taken || !selectedAffectedDevice || !form.actual_repair_location"
                                size="sm"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium w-full sm:w-auto px-5"
                            >
                                {{ form.processing ? 'Mengirim...' : 'Kirim' }}
                            </Button>
                        </div>
                    </div>

                </Card>
            </form>

        </div>
    </AuthenticatedLayout>
</template>
