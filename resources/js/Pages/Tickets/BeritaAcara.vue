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
    getInfrastructureLabel,
    formatDateWithWita as formatDate,
    getRevisionInfo 
} from '@/lib/ticket-helpers';
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue';
import {
    ArrowLeft,
    Paperclip,
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

const revisionInfo = computed(() => getRevisionInfo(props.ticket));

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
    <Head :title="`Rincian Teknis - #${ticket.ticket_number}`" />

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
                    Rincian Teknis Penanganan
                </h1>
            </div>

            <!-- Banner Catatan Revisi (Khusus Teknisi bila ada revisi aktif) -->
            <div v-if="role === 'technician' && revisionInfo" class="p-3.5 sm:p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900 space-y-1">
                <p class="font-bold text-amber-950">Permintaan Revisi dari Administrator</p>
                <p class="text-amber-800 text-xs sm:text-sm leading-relaxed">
                    {{ revisionInfo.instruction }}
                </p>
            </div>

            <!-- Main Technical Docket Card -->
            <Card class="border-slate-200 shadow-xs bg-white rounded-xl">
                
                <!-- Header Kartu: Judul Dokumen -->
                <div class="px-5 py-4 sm:px-6 sm:py-4.5 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight">
                        Dokumen Rincian Teknis
                    </h2>
                    <span class="font-mono text-xs font-semibold text-slate-500">
                        #{{ ticket.ticket_number }}
                    </span>
                </div>

                <CardContent class="p-5 sm:p-6 space-y-5">
                    
                    <!-- SEKSI 1: IDENTIFIKASI TEKNIS LAPANGAN -->
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 pb-1.5 mb-3 border-b border-slate-200 uppercase tracking-wider">
                            Identifikasi Teknis Lapangan
                        </h3>

                        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-y-3 gap-x-5">
                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Perangkat / Komponen</dt>
                                <dd class="text-sm font-semibold text-slate-900 mt-0.5">
                                    {{ ticket.affected_device || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Titik Lokasi Perbaikan</dt>
                                <dd class="text-sm font-semibold text-slate-900 mt-0.5">
                                    {{ ticket.actual_repair_location || ticket.location_details || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Jenis Infrastruktur</dt>
                                <dd class="text-sm font-semibold text-slate-900 mt-0.5">
                                    {{ (ticket.infrastructure_type || ticket.network_type) ? getInfrastructureLabel(ticket.infrastructure_type || ticket.network_type) : '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Kategori Masalah</dt>
                                <dd class="text-sm font-semibold text-slate-900 mt-0.5">
                                    {{ ticket.category?.name || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- SEKSI 2: PEMERIKSAAN & TINDAKAN TEKNIS (Hasil Pemeriksaan, Penyebab Utama, Langkah Tindakan, Hasil Pengujian, Parameter) -->
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 pb-1.5 mb-3 border-b border-slate-200 uppercase tracking-wider">
                            Pemeriksaan & Tindakan Teknis
                        </h3>

                        <dl class="grid grid-cols-1 md:grid-cols-2 gap-y-3.5 gap-x-5">
                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Hasil Pemeriksaan Awal (Kondisi Lapangan)</dt>
                                <dd class="text-sm text-slate-800 whitespace-pre-wrap mt-0.5 leading-relaxed">
                                    {{ ticket.inspection_result || '-' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Penyebab Utama Gangguan (Root Cause)</dt>
                                <dd class="text-sm text-slate-800 whitespace-pre-wrap mt-0.5 leading-relaxed">
                                    {{ ticket.root_cause || '-' }}
                                </dd>
                            </div>

                            <div class="md:col-span-2">
                                <dt class="text-xs text-slate-500 font-medium">Rincian Tindakan Penanganan / Perbaikan</dt>
                                <dd class="text-sm font-mono text-slate-800 whitespace-pre-wrap mt-0.5 leading-relaxed">
                                    {{ ticket.action_taken || ticket.resolution_note || '-' }}
                                </dd>
                                <div v-if="ticket.resolution_note && ticket.action_taken && ticket.resolution_note !== ticket.action_taken" class="mt-2 text-xs text-slate-600">
                                    <span class="font-medium text-slate-700">Catatan Tambahan:</span> {{ ticket.resolution_note }}
                                </div>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Status Hasil Pengujian</dt>
                                <dd class="text-sm font-semibold text-slate-900 mt-0.5">
                                    {{ ticket.test_result || 'Normal / Berfungsi Baik' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-xs text-slate-500 font-medium">Parameter Pengujian</dt>
                                <dd class="text-sm font-mono text-slate-800 whitespace-pre-wrap mt-0.5">
                                    {{ ticket.test_parameters || '-' }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- SEKSI 3: MATERIAL & SUKU CADANG (Format Ringkas, Tanpa Tabel & Tanpa Badge BG) -->
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 pb-1.5 mb-2.5 border-b border-slate-200 uppercase tracking-wider">
                            Material & Suku Cadang
                        </h3>

                        <div v-if="parsedMaterialsList.length > 0" class="text-sm text-slate-800 flex flex-wrap gap-x-4 gap-y-1.5">
                            <span v-for="(mat, idx) in parsedMaterialsList" :key="idx" class="inline-flex items-center gap-1.5">
                                <span class="font-medium text-slate-900">{{ mat.material }}</span>
                                <span class="text-slate-500 text-xs">({{ mat.quantity }} {{ mat.unit }})</span>
                                <span v-if="idx < parsedMaterialsList.length - 1" class="text-slate-300 ml-2.5">•</span>
                            </span>
                        </div>
                        <p v-else-if="ticket.materials_used" class="text-sm text-slate-800">
                            {{ ticket.materials_used }}
                        </p>
                        <p v-else class="text-xs text-slate-400 italic">
                            Tidak ada material atau suku cadang yang digunakan dalam penanganan ini.
                        </p>
                    </div>

                    <!-- SEKSI 4: DOKUMENTASI FOTO BUKTI PERBAIKAN -->
                    <div v-if="resolutionProofs.length > 0">
                        <h3 class="text-xs font-bold text-slate-900 pb-1.5 mb-2.5 border-b border-slate-200 uppercase tracking-wider">
                            Dokumentasi Foto Bukti Perbaikan
                        </h3>

                        <div class="flex flex-wrap gap-2">
                            <button 
                                type="button"
                                v-for="(att, idx) in resolutionProofs" 
                                :key="att.id"
                                @click="openImagePreview(resolutionProofs.map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                class="inline-flex items-center gap-2 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs text-slate-700 hover:text-slate-900 hover:border-slate-300 bg-slate-50 hover:bg-white transition-colors cursor-pointer"
                            >
                                <Paperclip class="w-3.5 h-3.5 text-slate-400" />
                                <span class="truncate max-w-[200px]">{{ att.file_name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- SEKSI 5: VERIFIKASI MUTU ADMINISTRATOR (Bila Tiket Telah Ditutup / Closed) -->
                    <div v-if="approvalInfo">
                        <h3 class="text-xs font-bold text-slate-900 pb-1.5 mb-2.5 border-b border-slate-200 uppercase tracking-wider">
                            Verifikasi Mutu Administrator
                        </h3>

                        <div class="space-y-1 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-slate-500">
                                <span class="font-semibold text-slate-800 text-sm">Verifikator: {{ approvalInfo.adminName }}</span>
                                <span v-if="approvalInfo.approvedAt" class="font-mono text-xs text-slate-500">{{ formatDate(approvalInfo.approvedAt) }}</span>
                            </div>
                            <p class="text-slate-700 text-sm italic pt-0.5">"{{ approvalInfo.comment }}"</p>
                        </div>
                    </div>

                </CardContent>

                <!-- Footer Aksi -->
                <div class="px-5 py-4 sm:px-6 sm:py-4 border-t border-slate-200 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-slate-500">
                        Dokumen Rincian Teknis Resmi Diskominfo Kota Palu.
                    </div>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                        <Link :href="route('tickets.show', ticket.id)" class="w-full sm:w-auto">
                            <Button type="button" variant="outline" size="sm" class="w-full sm:w-auto cursor-pointer">
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
                            class="border-amber-300 text-amber-900 bg-amber-50 hover:bg-amber-100 w-full sm:w-auto cursor-pointer font-medium"
                        >
                            Minta Revisi
                        </Button>

                        <Button 
                            v-if="canApprove" 
                            type="button" 
                            size="sm" 
                            @click="isApproveModalOpen = true"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium w-full sm:w-auto cursor-pointer shadow-xs"
                        >
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
                        Tiket akan dikembalikan ke tim teknisi untuk dilakukan perbaikan ulang atau melengkapi data rincian teknis.
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
