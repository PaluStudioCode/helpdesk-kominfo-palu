<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
    getStatusLabel,
    getStatusColor,
    getPriorityLabel, 
    getPriorityColor, 
    getInfrastructureLabel,
    formatDateWithWita as formatDate 
} from '@/lib/ticket-helpers';
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue';
import {
    ArrowLeft,
    Paperclip,
    CheckCircle2,
    RotateCcw,
} from 'lucide-vue-next';

const props = defineProps<{
    ticket: any;
}>();

const page = usePage();
const currentUser = computed(() => page.props.auth.user as any);
const role = computed(() => currentUser.value?.role);

// Parse materials used string to structured list
interface MaterialRow {
    material: string;
    quantity: number | null;
    unit: string;
}

const parsedMaterialsList = computed<MaterialRow[]>(() => {
    const str = props.ticket.materials_used;
    if (!str || !str.trim()) return [];

    const items = str.split(/,|\n/).map((s: string) => s.trim()).filter(Boolean);
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

        if (rawName && rawName.toLowerCase() !== 'none') {
            rows.push({
                material: rawName,
                quantity: isNaN(qty) ? 1 : qty,
                unit: unit || 'pcs',
            });
        }
    }

    return rows;
});

// Resolution proofs attachments
const resolutionProofs = computed(() => {
    if (!props.ticket.attachments) return [];
    return props.ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof');
});

// Approval Info
const approvalInfo = computed(() => {
    if (props.ticket.status !== 'closed' || !props.ticket.status_histories) return null;
    const closedHistories = props.ticket.status_histories.filter((h: any) => h.new_status === 'closed');
    if (closedHistories.length === 0) return null;
    const latestClosed = closedHistories.reduce((prev: any, curr: any) => {
        return (new Date(curr.created_at).getTime() > new Date(prev.created_at).getTime()) ? curr : prev;
    }, closedHistories[0]);

    return {
        adminName: latestClosed.changer?.name || 'Administrator Diskominfo',
        comment: latestClosed.comment || 'Admin memverifikasi mutu hasil perbaikan dan menutup tiket secara resmi.',
        approvedAt: latestClosed.created_at,
    };
});

// Image preview gallery modal
const previewModalOpen = ref(false);
const previewImages = ref<Array<{ url: string; name: string }>>([]);
const previewInitialIndex = ref(0);

const openImagePreview = (imagesList: Array<{ url: string; name: string }>, index: number = 0) => {
    previewImages.value = imagesList;
    previewInitialIndex.value = index;
    previewModalOpen.value = true;
};

// Admin Actions on Pending Approval
const canApprove = computed(() => role.value === 'admin' && props.ticket.status === 'pending_approval');
const canRequestRevision = computed(() => role.value === 'admin' && props.ticket.status === 'pending_approval');

const isApproveModalOpen = ref(false);
const isRevisionModalOpen = ref(false);

const approveForm = useForm({
    admin_note: '',
});

const submitApprove = () => {
    approveForm.post(route('tickets.approve-resolution', props.ticket.id), {
        onSuccess: () => {
            isApproveModalOpen.value = false;
        }
    });
};

const revisionForm = useForm({
    comment: '',
});

const submitRevision = () => {
    revisionForm.post(route('tickets.request-revision', props.ticket.id), {
        onSuccess: () => {
            isRevisionModalOpen.value = false;
        }
    });
};
</script>

<template>
    <Head :title="`Berita Acara - #${ticket.ticket_number}`" />

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
                    Berita Acara & Rincian Teknis Penanganan
                </h1>
            </div>

            <!-- Main Unified Docket Card (Format Dokumen Formal & Rapi) -->
            <Card class="border-slate-200 shadow-xs bg-white rounded-xl overflow-hidden">
                
                <!-- Header Kartu Utama -->
                <div class="px-5 py-3.5 border-b border-slate-200 bg-slate-50/75 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="text-[11px] font-medium text-slate-500 block">Subjek Gangguan:</span>
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight leading-snug truncate">
                            {{ ticket.title }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 text-xs sm:text-sm flex-wrap">
                        <div>
                            <span class="text-slate-400">Nomor:</span>
                            <span class="font-mono font-bold text-slate-900 ml-1">{{ ticket.ticket_number }}</span>
                        </div>
                        <span class="text-slate-300">•</span>
                        <div>
                            <span class="text-slate-400">Status:</span>
                            <span :class="['ml-1 font-medium', getStatusColor(ticket.status)]">
                                {{ getStatusLabel(ticket.status) }}
                            </span>
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
                                <dd class="text-slate-900 font-semibold mt-0.5 text-xs sm:text-sm truncate">{{ ticket.reporter?.name || ticket.user?.name || '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 font-medium">Tim Teknisi Lapangan</dt>
                                <dd class="text-slate-900 font-medium mt-0.5 text-xs sm:text-sm truncate">
                                    <span v-if="ticket.technicians && ticket.technicians.length > 0">
                                        {{ ticket.technicians.map((t: any) => t.name).join(', ') }}
                                    </span>
                                    <span v-else-if="ticket.assignee">
                                        {{ ticket.assignee.name }}
                                    </span>
                                    <span v-else class="text-slate-400 italic">
                                        -
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 font-medium">Waktu Selesai Penanganan</dt>
                                <dd class="text-slate-800 font-semibold mt-0.5 text-xs sm:text-sm">{{ ticket.resolved_at ? formatDate(ticket.resolved_at) : '-' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Baris 1: Identifikasi Lapangan & Lokasi Riil -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200">
                            <span class="text-[11px] font-medium text-slate-500 block">Perangkat / Komponen Terdampak</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-900 mt-0.5 block">
                                {{ ticket.affected_device || '-' }}
                            </span>
                        </div>

                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200">
                            <span class="text-[11px] font-medium text-slate-500 block">Titik Lokasi Riil Perbaikan</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-900 mt-0.5 block">
                                {{ ticket.actual_repair_location || ticket.location_details || '-' }}
                            </span>
                        </div>

                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200">
                            <span class="text-[11px] font-medium text-slate-500 block">Jenis Infrastruktur Riil</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-900 mt-0.5 block">
                                {{ (ticket.infrastructure_type || ticket.network_type) ? getInfrastructureLabel(ticket.infrastructure_type || ticket.network_type) : '-' }}
                            </span>
                        </div>
                    </div>

                    <!-- Baris 2: Diagnosa Lapangan & Akar Masalah (2 Kolom Sejajar) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200 space-y-1">
                            <span class="text-[11px] font-medium text-slate-500 block">Hasil Pemeriksaan Awal (Kondisi Lapangan)</span>
                            <p class="text-xs sm:text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">
                                {{ ticket.inspection_result || '-' }}
                            </p>
                        </div>

                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200 space-y-1">
                            <span class="text-[11px] font-medium text-slate-500 block">Penyebab Utama Gangguan (Root Cause)</span>
                            <p class="text-xs sm:text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">
                                {{ ticket.root_cause || '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Baris 3: Rincian Tindakan Penanganan / Perbaikan Teknis -->
                    <div class="p-3.5 bg-slate-50/60 rounded-lg border border-slate-200 space-y-1.5">
                        <span class="text-[11px] font-medium text-slate-500 block">Rincian Tindakan Penanganan / Perbaikan Teknis</span>
                        <div class="text-xs sm:text-sm font-mono text-slate-800 leading-relaxed whitespace-pre-wrap">
                            {{ ticket.action_taken || ticket.resolution_note || '-' }}
                        </div>
                        <div v-if="ticket.resolution_note && ticket.action_taken && ticket.resolution_note !== ticket.action_taken" class="mt-2 pt-2 border-t border-slate-200 text-xs text-slate-600">
                            <span class="font-semibold text-slate-800">Catatan Tambahan:</span> {{ ticket.resolution_note }}
                        </div>
                    </div>

                    <!-- Baris 4: Material & Suku Cadang yang Digunakan -->
                    <div class="space-y-1.5">
                        <span class="text-xs font-semibold text-slate-700 block">Material & Suku Cadang yang Digunakan</span>
                        <div v-if="parsedMaterialsList.length > 0" class="border border-slate-200 rounded-lg overflow-hidden">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                                    <tr>
                                        <th class="py-2 px-3 font-semibold">Nama Barang / Suku Cadang</th>
                                        <th class="py-2 px-3 font-semibold text-right w-36">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <tr v-for="(mat, idx) in parsedMaterialsList" :key="idx">
                                        <td class="py-2 px-3 text-slate-800">{{ mat.material }}</td>
                                        <td class="py-2 px-3 text-right font-semibold text-slate-900">
                                            {{ mat.quantity }} <span class="text-slate-500 font-normal">{{ mat.unit }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else-if="ticket.materials_used" class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs text-slate-700">
                            {{ ticket.materials_used }}
                        </div>
                        <div v-else class="p-3 bg-slate-50 rounded-lg border border-dashed border-slate-200 text-xs text-slate-400 italic text-center">
                            Tidak ada material atau suku cadang yang digunakan dalam perbaikan ini.
                        </div>
                    </div>

                    <!-- Baris 5: Hasil Pengujian & Parameter Teknis Akhir -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200">
                            <span class="text-[11px] font-medium text-slate-500 block">Status Hasil Pengujian Akhir</span>
                            <span class="text-xs sm:text-sm font-semibold text-emerald-700 mt-0.5 block">
                                {{ ticket.test_result || 'Normal / Berfungsi Baik' }}
                            </span>
                        </div>

                        <div class="p-3 bg-slate-50/60 rounded-lg border border-slate-200 space-y-1">
                            <span class="text-[11px] font-medium text-slate-500 block">Parameter Pengujian (Nilai Riil)</span>
                            <p class="text-xs sm:text-sm font-mono text-slate-800 whitespace-pre-wrap leading-relaxed">
                                {{ ticket.test_parameters || '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Baris 6: Dokumentasi Foto Hasil Perbaikan Lapangan -->
                    <div v-if="resolutionProofs.length > 0" class="space-y-2">
                        <span class="text-xs font-semibold text-slate-700 block">Dokumentasi Foto Bukti Perbaikan Lapangan</span>
                        <div class="flex flex-wrap gap-2.5">
                            <button 
                                type="button"
                                v-for="(att, idx) in resolutionProofs" 
                                :key="att.id"
                                @click="openImagePreview(resolutionProofs.map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                class="inline-flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg text-xs text-slate-700 hover:text-kominfo-primary hover:border-slate-300 bg-slate-50 hover:bg-white transition-colors cursor-pointer"
                            >
                                <Paperclip class="w-3.5 h-3.5 text-slate-400" />
                                <span class="truncate max-w-[220px] font-medium">{{ att.file_name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Baris 7: Pengesahan & Verifikasi Mutu Admin (Bila Selesai / Closed) -->
                    <div v-if="approvalInfo" class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 space-y-1 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-slate-500">
                            <span class="font-semibold text-slate-800">Verifikator Mutu: {{ approvalInfo.adminName }}</span>
                            <span v-if="approvalInfo.approvedAt" class="font-mono text-[11px] text-slate-500">{{ formatDate(approvalInfo.approvedAt) }}</span>
                        </div>
                        <p class="text-slate-800 italic pt-1">"{{ approvalInfo.comment }}"</p>
                    </div>

                </CardContent>

                <!-- Footer Aksi -->
                <div class="px-5 py-4 sm:px-6 sm:py-4 border-t border-slate-200 bg-slate-50/75 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-slate-500">
                        Dokumen Berita Acara Perbaikan Resmi Diskominfo Kota Palu.
                    </div>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                        <Link :href="route('tickets.show', ticket.id)" class="w-full sm:w-auto">
                            <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto">
                                Kembali ke Detail Tiket
                            </Button>
                        </Link>
                        
                        <!-- Admin Actions for Pending Approval -->
                        <Button 
                            v-if="canRequestRevision" 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            @click="isRevisionModalOpen = true"
                            class="border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100 w-full sm:w-auto"
                        >
                            <RotateCcw class="w-3.5 h-3.5 mr-1 text-amber-700" />
                            Minta Revisi
                        </Button>

                        <Button 
                            v-if="canApprove" 
                            type="button" 
                            size="sm"
                            @click="isApproveModalOpen = true"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium w-full sm:w-auto"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5 mr-1" />
                            Setujui Hasil Kerja
                        </Button>
                    </div>
                </div>

            </Card>

        </div>

        <!-- Modals for Admin Approval & Revision -->
        <!-- 1. Modal Setujui Hasil Kerja (Admin) -->
        <Dialog v-if="canApprove" v-model:open="isApproveModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Setujui Hasil Kerja & Tutup Tiket</DialogTitle>
                    <DialogDescription>
                        Konfirmasi bahwa hasil pekerjaan teknisi telah memenuhi standar mutu dan tiket akan ditutup secara resmi.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitApprove" class="space-y-4">
                    <div>
                        <InputLabel for="admin_note" value="Catatan Penutupan Admin (Opsional)" class="text-xs font-semibold text-slate-700" />
                        <Textarea 
                            id="admin_note" 
                            v-model="approveForm.admin_note" 
                            placeholder="Cth: Hasil perbaikan telah diverifikasi dengan baik dan layanan normal kembali." 
                            rows="3"
                            class="mt-1.5 text-sm bg-white"
                        />
                        <InputError :message="approveForm.errors.admin_note" class="mt-1" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="isApproveModalOpen = false" class="cursor-pointer">Batal</Button>
                        <Button type="submit" :disabled="approveForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white cursor-pointer">
                            {{ approveForm.processing ? 'Memproses...' : 'Setujui & Tutup Tiket' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 2. Modal Minta Revisi (Admin) -->
        <Dialog v-if="canRequestRevision" v-model:open="isRevisionModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Permintaan Revisi / Perbaikan Ulang</DialogTitle>
                    <DialogDescription>
                        Tiket akan dikembalikan ke tim teknisi untuk dilakukan perbaikan ulang atau melengkapi data berita acara.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitRevision" class="space-y-4">
                    <div>
                        <InputLabel for="revision_comment" value="Catatan / Arahan Revisi *" class="text-xs font-semibold text-slate-700" />
                        <Textarea 
                            id="revision_comment" 
                            v-model="revisionForm.comment" 
                            placeholder="Jelaskan bagian yang perlu diperbaiki ulang atau bukti foto yang belum lengkap..." 
                            rows="3"
                            class="mt-1.5 text-sm bg-white"
                            required
                        />
                        <InputError :message="revisionForm.errors.comment" class="mt-1" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="isRevisionModalOpen = false" class="cursor-pointer">Batal</Button>
                        <Button type="submit" :disabled="revisionForm.processing || !revisionForm.comment" class="bg-amber-600 hover:bg-amber-700 text-white cursor-pointer">
                            {{ revisionForm.processing ? 'Memproses...' : 'Kirim Permintaan Revisi' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Top Layer Image Lightbox Preview Modal with Gallery Support -->
        <ImagePreviewModal 
            v-model:open="previewModalOpen" 
            :images="previewImages" 
            :initialIndex="previewInitialIndex" 
        />

    </AuthenticatedLayout>
</template>
