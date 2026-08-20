<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import FileUpload from '@/Components/FileUpload.vue';
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Clock, 
    User, 
    Building2, 
    MapPin, 
    Paperclip, 
    History,
    MessageSquare,
    AlertCircle,
    UserCheck,
    CheckCircle2,
    RotateCcw,
    XCircle,
    Send,
    ArrowLeft,
    Shield,
    Activity,
    Calendar,
    Layers,
    FileText
} from 'lucide-vue-next';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    ticket: any;
}>();

const currentUser = computed(() => usePage().props.auth.user as any);
const role = computed(() => currentUser.value?.role);

// Permissions computed directly from Policies
const isDepartmentMatch = computed(() => {
    if (!currentUser.value?.department_id || !props.ticket.department_id) return false;
    return Number(currentUser.value.department_id) === Number(props.ticket.department_id);
});

// Display technician PIC reactive helper
const assignedTechnician = computed(() => {
    if (props.ticket.assignee) {
        return props.ticket.assignee;
    }
    // Fallback: If assigned_to matches current user
    if (props.ticket.assigned_to && currentUser.value && Number(props.ticket.assigned_to) === Number(currentUser.value.id)) {
        return currentUser.value;
    }
    return null;
});

const canAssign = computed(() => ['open'].includes(props.ticket.status) && ['admin', 'technician'].includes(role.value));
const canResolve = computed(() => props.ticket.status === 'in_progress' && (role.value === 'admin' || (role.value === 'technician' && Number(props.ticket.assigned_to) === Number(currentUser.value?.id))));
const canClose = computed(() => props.ticket.status === 'resolved' && (role.value === 'admin' || (role.value === 'opd_user' && isDepartmentMatch.value)));
const canReopen = computed(() => props.ticket.status === 'resolved' && (role.value === 'admin' || (role.value === 'opd_user' && isDepartmentMatch.value)));
const canCancel = computed(() => (role.value === 'admin' && ['open', 'in_progress'].includes(props.ticket.status)) || (role.value === 'opd_user' && props.ticket.status === 'open' && isDepartmentMatch.value));
const canReply = computed(() => !['closed', 'cancelled'].includes(props.ticket.status) && (['admin', 'technician'].includes(role.value) || (role.value === 'opd_user' && isDepartmentMatch.value)));

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }) + ' WITA';
};

const getSlaStatus = (ticket: any) => {
    if (!ticket.due_at) return null;
    
    if (['resolved', 'closed'].includes(ticket.status)) {
        return {
            status: 'completed',
            label: '✓ Kendala sudah selesai ditangani',
            textColor: 'text-emerald-700 font-medium'
        };
    }

    if (ticket.status === 'cancelled') {
        return {
            status: 'cancelled',
            label: '• Tiket dibatalkan',
            textColor: 'text-slate-500'
        };
    }
    
    const now = new Date();
    const dueAt = new Date(ticket.due_at);
    const diffHours = (dueAt.getTime() - now.getTime()) / (1000 * 60 * 60);

    if (diffHours < 0) {
        return { 
            status: 'danger', 
            label: '⚠️ Melewati batas target penanganan',
            textColor: 'text-rose-600 font-medium'
        };
    }
    if (diffHours <= 2) {
        const remainingMinutes = Math.max(1, Math.round(diffHours * 60));
        return { 
            status: 'warning', 
            label: `⏳ Waktu segera habis (sisa ${remainingMinutes} menit)`,
            textColor: 'text-amber-600 font-medium'
        };
    }
    return { 
        status: 'safe', 
        label: '✓ Masih dalam batas waktu target',
        textColor: 'text-emerald-600'
    };
};

// Modals State
const isAssignModalOpen = ref(false);
const isResolveModalOpen = ref(false);
const isCloseModalOpen = ref(false);
const isReopenModalOpen = ref(false);
const isCancelModalOpen = ref(false);
const isDrawerOpen = ref(false);

// Active Tab in Feed (Discussion vs Status History)
const activeTab = ref<'discussion' | 'history'>('discussion');

// Image Preview Gallery Lightbox State
const previewModalOpen = ref(false);
const previewImages = ref<Array<{ url: string; name: string }>>([]);
const previewInitialIndex = ref(0);

const openImagePreview = (imagesList: Array<{ url: string; name: string }>, index: number = 0) => {
    previewImages.value = imagesList;
    previewInitialIndex.value = index;
    previewModalOpen.value = true;
};

// Forms
const assignForm = useForm({
    assigned_to: ''
});

const resolveForm = useForm({
    status: 'resolved',
    resolution_note: '',
    resolution_proofs: [] as File[]
});

const closeForm = useForm({
    status: 'closed',
});

const reopenForm = useForm({
    status: 'in_progress',
    comment: ''
});

const cancelForm = useForm({
    status: 'cancelled',
    comment: ''
});

const submitAssign = () => {
    assignForm.post(route('tickets.assign', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            isAssignModalOpen.value = false;
        }
    });
};

const submitResolve = () => {
    resolveForm.post(route('tickets.status.update', props.ticket.id), {
        onSuccess: () => {
            isResolveModalOpen.value = false;
            resolveForm.reset();
        }
    });
};

const submitClose = () => {
    closeForm.post(route('tickets.status.update', props.ticket.id), {
        onSuccess: () => isCloseModalOpen.value = false
    });
};

const submitReopen = () => {
    reopenForm.post(route('tickets.status.update', props.ticket.id), {
        onSuccess: () => {
            isReopenModalOpen.value = false;
            reopenForm.reset();
        }
    });
};

const submitCancel = () => {
    cancelForm.post(route('tickets.status.update', props.ticket.id), {
        onSuccess: () => {
            isCancelModalOpen.value = false;
            cancelForm.reset();
        }
    });
};

// Reply Form
const replyFileInput = ref<HTMLInputElement | null>(null);
const replyForm = useForm({
    message: '',
    is_internal: false,
    attachments: [] as File[]
});

const triggerReplyFileSelect = () => {
    replyFileInput.value?.click();
};

const handleReplyFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (!target.files) return;

    replyForm.clearErrors('attachments');
    const selected = Array.from(target.files);
    const availableSlots = 3 - replyForm.attachments.length;

    if (selected.length > availableSlots) {
        replyForm.setError('attachments', `Maksimal hanya dapat melampirkan 3 gambar. Tersisa ${availableSlots} slot.`);
    }

    const validFiles: File[] = [];
    const maxSizeBytes = 5 * 1024 * 1024;
    const allowedExts = ['jpg', 'jpeg', 'png'];

    for (const file of selected.slice(0, Math.max(0, availableSlots))) {
        const ext = file.name.split('.').pop()?.toLowerCase() || '';
        if (!file.type.startsWith('image/') && !allowedExts.includes(ext)) {
            replyForm.setError('attachments', `File "${file.name}" bukan gambar! Hanya format JPG, JPEG, atau PNG yang diperbolehkan.`);
            continue;
        }

        if (file.size > maxSizeBytes) {
            replyForm.setError('attachments', `Ukuran gambar "${file.name}" melebihi batas maksimal 5 MB.`);
            continue;
        }

        validFiles.push(file);
    }

    if (validFiles.length > 0) {
        replyForm.attachments = [...replyForm.attachments, ...validFiles];
    }
    
    // Reset file input value to allow selecting the same file again if needed
    target.value = '';
};

const removeReplyAttachment = (index: number) => {
    replyForm.attachments.splice(index, 1);
};

const submitReply = () => {
    replyForm.post(route('tickets.replies.store', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset();
        }
    });
};
</script>

<template>
    <Head :title="`Tiket ${ticket.ticket_number}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto space-y-5">
            <!-- Unified Sticky Action Header -->
            <div class="bg-white rounded-lg border border-slate-200 p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <Link :href="route('tickets.index')" class="p-1.5 rounded-md hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors" title="Kembali ke Antrean">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <span class="text-lg font-bold text-slate-900 font-mono tracking-tight">{{ ticket.ticket_number }}</span>
                    <StatusBadge type="ticket" :status="ticket.status" />
                </div>

                <!-- Action Buttons Container -->
                <div class="flex flex-wrap items-center gap-2">
                    <Button @click="isDrawerOpen = true" size="sm" variant="outline" class="border-slate-300 text-slate-700 hover:bg-slate-50 relative">
                        <MessageSquare class="w-3.5 h-3.5 mr-1.5 text-kominfo-primary" />
                        <span>Diskusi & Riwayat</span>
                        <span v-if="ticket.replies.length > 0" class="ml-1.5 px-1.5 py-0.2 bg-blue-100 text-kominfo-primary font-bold text-[10px] rounded-full">
                            {{ ticket.replies.length }}
                        </span>
                    </Button>

                    <Button v-if="canAssign" @click="isAssignModalOpen = true" size="sm" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white">
                        <UserCheck class="w-3.5 h-3.5 mr-1.5" /> Ambil Tiket
                    </Button>
                    
                    <Button v-if="canResolve" @click="isResolveModalOpen = true" size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                        <CheckCircle2 class="w-3.5 h-3.5 mr-1.5" /> Selesaikan Tiket
                    </Button>

                    <Button v-if="canClose" @click="isCloseModalOpen = true" size="sm" class="bg-slate-900 hover:bg-slate-800 text-white">
                        <CheckCircle2 class="w-3.5 h-3.5 mr-1.5" /> Konfirmasi Selesai
                    </Button>

                    <Button v-if="canReopen" @click="isReopenModalOpen = true" size="sm" variant="outline" class="border-amber-300 text-amber-800 hover:bg-amber-50">
                        <RotateCcw class="w-3.5 h-3.5 mr-1.5" /> Buka Kembali
                    </Button>

                    <Button v-if="canCancel" @click="isCancelModalOpen = true" size="sm" variant="destructive">
                        <XCircle class="w-3.5 h-3.5 mr-1.5" /> Batalkan
                    </Button>
                </div>
            </div>

            <!-- Single Unified Information Card -->
            <Card class="border-slate-200 shadow-sm bg-white overflow-hidden">
                <!-- Header: Title & Main Statuses -->
                <div class="p-5 sm:p-6 border-b border-slate-100 bg-slate-50/40">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <StatusBadge type="priority" :status="ticket.priority" />
                                <StatusBadge type="network" :status="ticket.network_type" />
                                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 border border-slate-200 font-medium text-slate-700">
                                    {{ ticket.category.name }}
                                </span>
                            </div>
                            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
                                {{ ticket.title }}
                            </h1>
                        </div>
                    </div>

                    <!-- Metadata Grid: OPD, Reporter, Dates, Technician, SLA -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-5 border-t border-slate-200/80 text-xs">
                        <!-- OPD & Location -->
                        <div class="flex items-start gap-2.5">
                            <Building2 class="w-4 h-4 text-kominfo-primary shrink-0 mt-0.5" />
                            <div>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Instansi & Lokasi</p>
                                <p class="font-semibold text-slate-800">{{ ticket.department.name }}</p>
                                <p class="text-slate-500 text-[11px] mt-0.5">{{ ticket.location_details }}</p>
                            </div>
                        </div>

                        <!-- Reporter & Report Date -->
                        <div class="flex items-start gap-2.5">
                            <User class="w-4 h-4 text-slate-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Pelapor & Tanggal</p>
                                <p class="font-semibold text-slate-800">{{ ticket.reporter.name }}</p>
                                <p class="text-slate-500 text-[11px] mt-0.5">{{ formatDate(ticket.created_at) }}</p>
                            </div>
                        </div>

                        <!-- SLA Deadline -->
                        <div class="flex items-start gap-2.5">
                            <Clock class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Target Penyelesaian</p>
                                <p class="font-semibold text-slate-800">{{ ticket.due_at ? formatDate(ticket.due_at) : '-' }}</p>
                                <p 
                                    v-if="getSlaStatus(ticket)" 
                                    class="text-[11px] mt-0.5 flex items-center gap-1"
                                    :class="getSlaStatus(ticket)?.textColor"
                                >
                                    {{ getSlaStatus(ticket)?.label }}
                                </p>
                            </div>
                        </div>

                        <!-- Assigned Technician -->
                        <div class="flex items-start gap-2.5">
                            <UserCheck class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                            <div>
                                <p class="text-slate-400 text-[10px] uppercase font-bold">Petugas Teknisi</p>
                                <div v-if="assignedTechnician" class="flex items-center gap-1.5 mt-0.5">
                                    <span class="font-semibold text-slate-800">{{ assignedTechnician.name }}</span>
                                </div>
                                <span v-else class="inline-block mt-0.5 text-amber-700 bg-amber-50 px-2 py-0.5 rounded text-[11px] font-medium border border-amber-200">
                                    Belum diambil
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Body -->
                <CardContent class="p-5 sm:p-6 space-y-6 text-sm">
                    <!-- Issue Description -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Deskripsi Gangguan</h3>
                        <div class="text-slate-700 leading-relaxed whitespace-pre-wrap bg-slate-50/70 p-4 rounded-xl border border-slate-100">
                            {{ ticket.description }}
                        </div>
                    </div>

                    <!-- Initial Problem Proof Attachments -->
                    <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').length > 0">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2.5">Foto Bukti Gangguan</h3>
                        <div class="flex flex-wrap gap-3">
                            <button 
                                type="button"
                                v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof')" 
                                :key="att.id"
                                @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                class="flex items-center gap-2.5 px-3.5 py-2.5 border border-slate-200 rounded-xl hover:border-kominfo-primary hover:bg-blue-50/40 bg-white text-xs text-slate-700 transition-all shadow-xs group cursor-pointer"
                            >
                                <div class="w-7 h-7 rounded-lg bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                                    <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                </div>
                                <span class="truncate max-w-[180px] font-medium group-hover:text-kominfo-primary">{{ att.file_name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Technician Resolution Card Banner (If Resolved or Closed) -->
                    <div v-if="ticket.resolution_note" class="p-5 sm:p-6 bg-emerald-50/70 border border-emerald-200/90 rounded-2xl space-y-4 shadow-xs">
                        <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-emerald-200/60">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-2">
                                <span class="p-1 rounded-md bg-emerald-100 text-emerald-700">
                                    <CheckCircle2 class="w-4 h-4" />
                                </span>
                                Catatan Solusi Perbaikan Teknisi
                            </h3>
                            <span v-if="ticket.resolved_at" class="text-xs text-emerald-800/80 font-medium bg-emerald-100/60 px-2.5 py-1 rounded-md">
                                Diselesaikan: {{ formatDate(ticket.resolved_at) }}
                            </span>
                        </div>

                        <div class="text-sm text-emerald-950 leading-relaxed whitespace-pre-wrap font-normal">
                            {{ ticket.resolution_note }}
                        </div>

                        <!-- Resolution Proof Photos -->
                        <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').length > 0" class="pt-2 border-t border-emerald-200/50">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-800/70 mb-2.5">Foto Bukti Perbaikan</p>
                            <div class="flex flex-wrap gap-3">
                                <button 
                                    type="button"
                                    v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof')" 
                                    :key="att.id"
                                    @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                    class="flex items-center gap-2.5 px-3.5 py-2.5 bg-white border border-emerald-300 rounded-xl text-xs text-emerald-900 hover:bg-emerald-50 hover:border-emerald-400 transition-all cursor-pointer shadow-xs group"
                                >
                                    <div class="w-7 h-7 rounded-lg bg-emerald-100 overflow-hidden shrink-0 flex items-center justify-center border border-emerald-200">
                                        <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                    </div>
                                    <span class="truncate max-w-[160px] font-medium group-hover:text-emerald-700">{{ att.file_name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Assign Modal Dialog -->
        <Dialog v-model:open="isAssignModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Klaim Penugasan Tiket</DialogTitle>
                    <DialogDescription>
                        Anda akan mengambil alih penanganan tiket ini. Status tiket otomatis beralih menjadi In Progress.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitAssign">
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isAssignModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="assignForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white">
                            Ya, Ambil Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Resolve Modal Dialog -->
        <Dialog v-model:open="isResolveModalOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Selesaikan Penanganan Tiket</DialogTitle>
                    <DialogDescription>
                        Tuliskan tindakan perbaikan yang telah dilakukan dan unggah foto bukti jika ada.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitResolve" class="space-y-4">
                    <div class="space-y-1">
                        <InputLabel for="resolution_note" value="Catatan Solusi Perbaikan (Wajib)" />
                        <Textarea 
                            id="resolution_note"
                            v-model="resolveForm.resolution_note"
                            placeholder="Cth: Mengganti kabel patch cord dan melakukan konfigurasi ulang port switch..."
                            class="min-h-[90px] text-xs bg-white"
                        />
                        <InputError :message="resolveForm.errors.resolution_note" class="text-xs" />
                    </div>

                    <div class="space-y-1">
                        <InputLabel value="Foto Bukti Perbaikan (Opsional, Maks 3)" />
                        <FileUpload 
                            v-model="resolveForm.resolution_proofs"
                            :multiple="true"
                            :max-files="3"
                            accept="image/jpeg,image/png"
                        />
                        <InputError :message="resolveForm.errors.resolution_proofs" class="text-xs" />
                    </div>

                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isResolveModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="resolveForm.processing || !resolveForm.resolution_note" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            Tandai Selesai
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Close Modal Dialog -->
        <Dialog v-model:open="isCloseModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Konfirmasi Penyelesaian Tiket</DialogTitle>
                    <DialogDescription>
                        Apakah Anda memastikan bahwa kendala jaringan telah tertangani dengan baik dan tiket ini siap ditutup secara resmi?
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitClose">
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isCloseModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="closeForm.processing" class="bg-slate-900 hover:bg-slate-800 text-white">
                            Ya, Konfirmasi & Tutup
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reopen Modal Dialog -->
        <Dialog v-model:open="isReopenModalOpen">
            <DialogContent class="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Buka Kembali Tiket</DialogTitle>
                    <DialogDescription>
                        Jelaskan alasan pembukaan kembali tiket agar tim teknisi dapat melanjutkan penanganan kendala yang belum tuntas.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitReopen" class="space-y-3">
                    <div class="space-y-1">
                        <InputLabel for="reopen_comment" value="Alasan Pembukaan Kembali" />
                        <Textarea 
                            id="reopen_comment"
                            v-model="reopenForm.comment"
                            placeholder="Cth: Koneksi sempat pulih namun putus kembali setelah beberapa menit..."
                            class="min-h-[85px] text-xs bg-white"
                            required
                        />
                        <InputError :message="reopenForm.errors.comment" class="text-xs" />
                    </div>

                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isReopenModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="reopenForm.processing || !reopenForm.comment" class="bg-amber-600 hover:bg-amber-700 text-white">
                            Buka Kembali Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cancel Modal Dialog -->
        <Dialog v-model:open="isCancelModalOpen">
            <DialogContent class="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Batalkan Laporan Tiket</DialogTitle>
                    <DialogDescription>
                        Apakah Anda yakin ingin membatalkan tiket ini? Tiket yang dibatalkan tidak akan diproses lebih lanjut.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitCancel" class="space-y-3">
                    <div class="space-y-1">
                        <InputLabel for="cancel_comment" value="Alasan Pembatalan" />
                        <Textarea 
                            id="cancel_comment"
                            v-model="cancelForm.comment"
                            placeholder="Cth: Laporan terduplikasi atau kendala terselesaikan sendiri..."
                            class="min-h-[85px] text-xs bg-white"
                            required
                        />
                        <InputError :message="cancelForm.errors.comment" class="text-xs" />
                    </div>

                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isCancelModalOpen = false">Kembali</Button>
                        <Button type="submit" :disabled="cancelForm.processing || !cancelForm.comment" variant="destructive">
                            Ya, Batalkan Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Slide-over Drawer for Discussion & Activity Timeline -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div 
                    v-if="isDrawerOpen" 
                    class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-50 transition-opacity" 
                    @click="isDrawerOpen = false"
                />
            </Transition>

            <Transition
                enter-active-class="transform transition ease-in-out duration-300 sm:duration-500"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transform transition ease-in-out duration-300 sm:duration-500"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <div 
                    v-if="isDrawerOpen" 
                    class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white shadow-2xl flex flex-col border-l border-slate-200"
                >
                    <!-- Drawer Header -->
                    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-kominfo-primary">
                                <MessageSquare class="w-4 h-4" />
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Panel Diskusi & Riwayat</h3>
                                <p class="text-[11px] text-slate-500 font-mono">{{ ticket.ticket_number }}</p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="isDrawerOpen = false" 
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors"
                            title="Tutup Panel"
                        >
                            <XCircle class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Drawer Tabs Header -->
                    <div class="border-b border-slate-200 bg-white px-5 shrink-0">
                        <div class="flex space-x-6">
                            <button
                                type="button"
                                @click="activeTab = 'discussion'"
                                :class="[
                                    activeTab === 'discussion'
                                        ? 'border-kominfo-primary text-kominfo-primary font-bold'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                                    'inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs transition-all focus:outline-none'
                                ]"
                            >
                                <MessageSquare class="w-3.5 h-3.5" />
                                <span>Diskusi & Tanggapan</span>
                                <span 
                                    :class="[
                                        activeTab === 'discussion'
                                            ? 'bg-blue-100 text-kominfo-primary'
                                            : 'bg-slate-100 text-slate-600',
                                        'py-0.5 px-1.5 rounded-full text-[10px]'
                                    ]"
                                >
                                    {{ ticket.replies.length }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'history'"
                                :class="[
                                    activeTab === 'history'
                                        ? 'border-kominfo-primary text-kominfo-primary font-bold'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                                    'inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs transition-all focus:outline-none'
                                ]"
                            >
                                <History class="w-3.5 h-3.5" />
                                <span>Riwayat Status</span>
                                <span 
                                    :class="[
                                        activeTab === 'history'
                                            ? 'bg-blue-100 text-kominfo-primary'
                                            : 'bg-slate-100 text-slate-600',
                                        'py-0.5 px-1.5 rounded-full text-[10px]'
                                    ]"
                                >
                                    {{ ticket.status_histories.length }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Drawer Content: Discussion -->
                    <div v-show="activeTab === 'discussion'" class="flex-1 flex flex-col min-h-0 bg-slate-50/50">
                        <!-- Messages List -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-3">
                            <div v-if="ticket.replies.length === 0" class="text-center py-12 text-xs text-slate-400">
                                Belum ada aktivitas atau balasan diskusi pada tiket ini.
                            </div>

                            <div 
                                v-for="reply in ticket.replies" 
                                :key="reply.id" 
                                class="p-3 rounded-xl border text-xs space-y-1.5 transition-all shadow-xs"
                                :class="[
                                    reply.is_internal 
                                        ? 'bg-amber-50 border-amber-200 text-amber-900' 
                                        : Number(reply.user_id) === Number(currentUser.id)
                                            ? 'bg-blue-50/70 border-blue-200 text-slate-800 ml-4' 
                                            : 'bg-white border-slate-200 text-slate-800 mr-4'
                                ]"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 font-semibold">
                                        <span :class="reply.is_internal ? 'text-amber-900 font-bold' : 'text-slate-900'">
                                            {{ reply.user.name }}
                                        </span>
                                        <span v-if="reply.is_internal" class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-amber-200 text-amber-900">
                                            Catatan Internal
                                        </span>
                                        <span v-else class="text-[10px] capitalize text-slate-400 font-normal">
                                            ({{ reply.user.role.replace('_', ' ') }})
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-slate-400">{{ formatDate(reply.created_at) }}</span>
                                </div>
                                
                                <p class="text-xs leading-relaxed whitespace-pre-wrap">{{ reply.message }}</p>

                                <!-- Reply Attachments -->
                                <div v-if="reply.attachments && reply.attachments.length > 0" class="pt-1 flex flex-wrap gap-1.5">
                                    <button 
                                        type="button"
                                        v-for="(att, attIdx) in reply.attachments" 
                                        :key="att.id" 
                                        @click="openImagePreview(reply.attachments.map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), attIdx)"
                                        class="text-[10px] bg-white border border-slate-200 rounded px-2 py-1 flex items-center gap-1.5 hover:border-kominfo-primary hover:bg-slate-50 transition-colors text-slate-700 font-medium cursor-pointer shadow-xs"
                                    >
                                        <div class="w-3.5 h-3.5 rounded bg-slate-100 overflow-hidden shrink-0">
                                            <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                        </div>
                                        <span class="truncate max-w-[120px]">{{ att.file_name }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Reply Form at the Bottom of Drawer -->
                        <div v-if="canReply" class="p-4 bg-white border-t border-slate-200 shrink-0">
                            <form @submit.prevent="submitReply" class="space-y-2.5">
                                <Textarea 
                                    v-model="replyForm.message" 
                                    placeholder="Tulis pesan atau update progres..." 
                                    class="min-h-[70px] text-xs resize-y bg-white border-slate-200"
                                />
                                <InputError :message="replyForm.errors.message" class="text-xs" />
                                <InputError :message="replyForm.errors.attachments" class="text-xs" />

                                <!-- Selected Attachments Chips -->
                                <div v-if="replyForm.attachments.length > 0" class="flex flex-wrap gap-1.5">
                                    <div 
                                        v-for="(file, idx) in replyForm.attachments" 
                                        :key="idx"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 text-[10px]"
                                    >
                                        <Paperclip class="w-2.5 h-2.5 text-slate-400" />
                                        <span class="max-w-[120px] truncate font-medium">{{ file.name }}</span>
                                        <button 
                                            type="button" 
                                            @click="removeReplyAttachment(idx)" 
                                            class="text-slate-400 hover:text-rose-500 rounded p-0.5"
                                        >
                                            <XCircle class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>

                                <input 
                                    ref="replyFileInput" 
                                    type="file" 
                                    multiple 
                                    accept="image/jpeg,image/png"
                                    class="hidden" 
                                    @change="handleReplyFileChange" 
                                />

                                <div class="flex items-center justify-between gap-2 pt-1">
                                    <div class="flex items-center gap-3">
                                        <button 
                                            type="button" 
                                            @click="triggerReplyFileSelect"
                                            class="inline-flex items-center gap-1 text-[11px] text-slate-600 hover:text-kominfo-primary px-2 py-1 rounded border border-slate-200 bg-slate-50 hover:bg-white transition-colors"
                                            :disabled="replyForm.attachments.length >= 3"
                                        >
                                            <Paperclip class="w-3 h-3" />
                                            <span>Lampirkan</span>
                                            <span class="text-[9px] text-slate-400">({{ replyForm.attachments.length }}/3)</span>
                                        </button>

                                        <div v-if="['admin', 'technician'].includes(role)" class="flex items-center space-x-1.5">
                                            <Checkbox id="drawer_is_internal" v-model:checked="replyForm.is_internal" />
                                            <label for="drawer_is_internal" class="text-[11px] font-medium text-amber-800 cursor-pointer select-none">
                                                Internal
                                            </label>
                                        </div>
                                    </div>

                                    <Button type="submit" size="sm" :disabled="replyForm.processing || (!replyForm.message && replyForm.attachments.length === 0)" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white px-3 h-8 text-xs">
                                        <Send class="w-3 h-3 mr-1" /> Kirim
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div v-else-if="['closed', 'cancelled'].includes(ticket.status)" class="p-3 bg-slate-100 border-t border-slate-200 text-center text-xs text-slate-500 shrink-0">
                            Sesi diskusi telah ditutup ({{ ticket.status === 'closed' ? 'Closed' : 'Cancelled' }}).
                        </div>
                    </div>

                    <!-- Drawer Content: History Timeline -->
                    <div v-show="activeTab === 'history'" class="flex-1 overflow-y-auto p-5 bg-white">
                        <div v-if="ticket.status_histories.length === 0" class="text-center text-xs text-slate-400 py-12">
                            Belum ada catatan riwayat status.
                        </div>
                        <div v-else class="relative border-l-2 border-slate-200 ml-2.5 space-y-6">
                            <div v-for="history in ticket.status_histories" :key="history.id" class="relative pl-4 text-xs">
                                <div class="absolute w-2.5 h-2.5 bg-kominfo-primary rounded-full -left-[6px] top-1 border-2 border-white"></div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <StatusBadge type="ticket" :status="history.new_status" />
                                        <span class="text-[10px] text-slate-400 font-mono">{{ formatDate(history.created_at) }}</span>
                                    </div>
                                    <p class="text-xs text-slate-700 leading-relaxed">{{ history.comment }}</p>
                                    <p class="text-[10px] text-slate-400">Oleh: <span class="font-medium text-slate-600">{{ history.changer?.name || 'Sistem' }}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Top Layer Image Lightbox Preview Modal with Gallery Support -->
        <ImagePreviewModal 
            v-model:open="previewModalOpen" 
            :images="previewImages" 
            :initialIndex="previewInitialIndex" 
        />
    </AuthenticatedLayout>
</template>
