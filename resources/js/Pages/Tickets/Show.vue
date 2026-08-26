<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
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
    Lock,
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

const getRoleBadgeInfo = (userRole: string) => {
    switch (userRole) {
        case 'admin':
            return {
                label: 'Administrator',
                badgeClass: 'bg-blue-100 text-kominfo-primary border-blue-200'
            };
        case 'technician':
            return {
                label: 'Teknisi',
                badgeClass: 'bg-emerald-100 text-emerald-800 border-emerald-200'
            };
        case 'opd_user':
            return {
                label: 'Pelapor OPD',
                badgeClass: 'bg-slate-100 text-slate-700 border-slate-200'
            };
        default:
            return {
                label: userRole || 'User',
                badgeClass: 'bg-slate-100 text-slate-700 border-slate-200'
            };
    }
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

const ticketReplies = ref<any[]>([...props.ticket.replies]);
const discussionScrollContainer = ref<HTMLElement | null>(null);

watch(() => props.ticket.replies, (newReplies) => {
    ticketReplies.value = [...newReplies];
}, { deep: true });

const scrollToBottom = () => {
    nextTick(() => {
        if (discussionScrollContainer.value) {
            discussionScrollContainer.value.scrollTop = discussionScrollContainer.value.scrollHeight;
        }
    });
};

let echoChannel: any = null;

onMounted(() => {
    if (typeof window !== 'undefined' && (window as any).Echo) {
        echoChannel = (window as any).Echo.private(`ticket.${props.ticket.id}`);
        echoChannel.listen('.reply.created', (event: any) => {
            const newReply = event.reply;
            const exists = ticketReplies.value.some((r: any) => r.id === newReply.id);
            if (!exists) {
                ticketReplies.value.push(newReply);
                scrollToBottom();
            }
        });
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined' && (window as any).Echo) {
        (window as any).Echo.leave(`ticket.${props.ticket.id}`);
    }
    echoChannel = null;
});
</script>

<template>
    <Head :title="`Tiket ${ticket.ticket_number}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto space-y-5">
            <!-- Unified Sticky Action Header -->
            <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <Link :href="route('tickets.index')" class="p-2 rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-900 transition-colors" title="Kembali ke Antrean">
                        <ArrowLeft class="w-5 h-5" />
                    </Link>
                    <span class="text-xl sm:text-2xl font-bold text-slate-900 font-mono tracking-tight">{{ ticket.ticket_number }}</span>
                    <StatusBadge type="ticket" :status="ticket.status" class="text-sm px-3 py-1" />
                </div>

                <!-- Action Buttons Container -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Button @click="isDrawerOpen = true" size="default" variant="outline" class="border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-medium relative">
                        <MessageSquare class="w-4 h-4 mr-2 text-kominfo-primary" />
                        <span>Diskusi & Riwayat</span>
                        <span v-if="ticketReplies.length > 0" class="ml-2 px-2 py-0.5 bg-blue-100 text-kominfo-primary font-bold text-xs rounded-full">
                            {{ ticketReplies.length }}
                        </span>
                    </Button>

                    <Button v-if="canAssign" @click="isAssignModalOpen = true" size="default" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-sm font-medium">
                        <UserCheck class="w-4 h-4 mr-2" /> Ambil Tiket
                    </Button>
                    
                    <Button v-if="canResolve" @click="isResolveModalOpen = true" size="default" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
                        <CheckCircle2 class="w-4 h-4 mr-2" /> Selesaikan Tiket
                    </Button>

                    <Button v-if="canClose" @click="isCloseModalOpen = true" size="default" class="bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium">
                        <CheckCircle2 class="w-4 h-4 mr-2" /> Konfirmasi Selesai
                    </Button>

                    <Button v-if="canReopen" @click="isReopenModalOpen = true" size="default" variant="outline" class="border-amber-300 text-amber-800 hover:bg-amber-50 text-sm font-medium">
                        <RotateCcw class="w-4 h-4 mr-2" /> Buka Kembali
                    </Button>

                    <Button v-if="canCancel" @click="isCancelModalOpen = true" size="default" variant="destructive" class="text-sm font-medium">
                        <XCircle class="w-4 h-4 mr-2" /> Batalkan
                    </Button>
                </div>
            </div>

            <!-- Single Unified Information Card -->
            <Card class="border-slate-200 shadow-xs bg-white overflow-hidden rounded-xl">
                <!-- Header: Title & Main Statuses -->
                <div class="p-6 sm:p-7 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <StatusBadge type="priority" :status="ticket.priority" class="text-xs px-2.5 py-0.5" />
                                <StatusBadge type="network" :status="ticket.network_type" class="text-xs px-2.5 py-0.5" />
                                <span class="text-xs sm:text-sm px-3 py-1 rounded-full bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">
                                    {{ ticket.category.name }}
                                </span>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight leading-snug">
                                {{ ticket.title }}
                            </h1>
                        </div>
                    </div>

                    <!-- Metadata Grid: OPD, Reporter, Dates, Technician, SLA -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-6 pt-6 border-t border-slate-200 text-sm">
                        <!-- OPD & Location -->
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-blue-50 text-kominfo-primary shrink-0 mt-0.5 border border-blue-100">
                                <Building2 class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Instansi & Lokasi</p>
                                <p class="font-bold text-slate-900 text-sm sm:text-base mt-0.5">{{ ticket.department.name }}</p>
                                <p class="text-slate-600 text-xs sm:text-sm mt-0.5">{{ ticket.location_details }}</p>
                            </div>
                        </div>

                        <!-- Reporter & Report Date -->
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-slate-100 text-slate-600 shrink-0 mt-0.5 border border-slate-200">
                                <User class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Pelapor & Tanggal</p>
                                <p class="font-bold text-slate-900 text-sm sm:text-base mt-0.5">{{ ticket.reporter.name }}</p>
                                <p class="text-slate-600 text-xs sm:text-sm mt-0.5">{{ formatDate(ticket.created_at) }}</p>
                            </div>
                        </div>

                        <!-- SLA Deadline -->
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-amber-50 text-amber-600 shrink-0 mt-0.5 border border-amber-100">
                                <Clock class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Target Penyelesaian</p>
                                <p class="font-bold text-slate-900 text-sm sm:text-base mt-0.5">{{ ticket.due_at ? formatDate(ticket.due_at) : '-' }}</p>
                                <p 
                                    v-if="getSlaStatus(ticket)" 
                                    class="text-xs sm:text-sm mt-0.5 font-medium flex items-center gap-1"
                                    :class="getSlaStatus(ticket)?.textColor"
                                >
                                    {{ getSlaStatus(ticket)?.label }}
                                </p>
                            </div>
                        </div>

                        <!-- Assigned Technician -->
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600 shrink-0 mt-0.5 border border-emerald-100">
                                <UserCheck class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="text-slate-400 text-xs uppercase font-bold tracking-wider">Petugas Teknisi</p>
                                <div v-if="assignedTechnician" class="mt-0.5">
                                    <p class="font-bold text-slate-900 text-sm sm:text-base">{{ assignedTechnician.name }}</p>
                                    <p v-if="assignedTechnician.phone_number" class="text-slate-500 text-xs">{{ assignedTechnician.phone_number }}</p>
                                </div>
                                <span v-else class="inline-block mt-1 text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded-md text-xs font-semibold border border-amber-200">
                                    Belum diambil
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content Body -->
                <CardContent class="p-6 sm:p-7 space-y-7">
                    <!-- Issue Description -->
                    <div>
                        <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center gap-2">
                            <FileText class="w-4 h-4 text-slate-400" /> Deskripsi Gangguan
                        </h3>
                        <div class="text-slate-800 text-sm sm:text-base leading-relaxed whitespace-pre-wrap bg-slate-50/80 p-5 rounded-xl border border-slate-200">
                            {{ ticket.description }}
                        </div>
                    </div>

                    <!-- Initial Problem Proof Attachments -->
                    <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').length > 0">
                        <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-2">
                            <Paperclip class="w-4 h-4 text-slate-400" /> Foto Bukti Gangguan
                        </h3>
                        <div class="flex flex-wrap gap-3">
                            <button 
                                type="button"
                                v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof')" 
                                :key="att.id"
                                @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                class="flex items-center gap-3 px-4 py-3 border border-slate-200 rounded-xl hover:border-kominfo-primary hover:bg-blue-50/50 bg-white text-xs sm:text-sm text-slate-800 transition-all shadow-xs group cursor-pointer"
                            >
                                <div class="w-9 h-9 rounded-lg bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                                    <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                </div>
                                <span class="truncate max-w-[220px] font-semibold group-hover:text-kominfo-primary">{{ att.file_name }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Technician Resolution Card Banner (If Resolved or Closed) -->
                    <div v-if="ticket.resolution_note" class="p-6 sm:p-7 bg-emerald-50/80 border border-emerald-200 rounded-2xl space-y-4 shadow-xs">
                        <div class="flex items-center justify-between flex-wrap gap-2 pb-3 border-b border-emerald-200/80">
                            <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-2">
                                <span class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700">
                                    <CheckCircle2 class="w-5 h-5" />
                                </span>
                                Catatan Solusi Perbaikan Teknisi
                            </h3>
                            <span v-if="ticket.resolved_at" class="text-xs sm:text-sm text-emerald-900 font-semibold bg-emerald-100 px-3 py-1 rounded-lg">
                                Diselesaikan: {{ formatDate(ticket.resolved_at) }}
                            </span>
                        </div>

                        <div class="text-sm sm:text-base text-emerald-950 leading-relaxed whitespace-pre-wrap font-normal">
                            {{ ticket.resolution_note }}
                        </div>

                        <!-- Resolution Proof Photos -->
                        <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').length > 0" class="pt-3 border-t border-emerald-200">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-900 mb-3">Foto Bukti Perbaikan</p>
                            <div class="flex flex-wrap gap-3">
                                <button 
                                    type="button"
                                    v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof')" 
                                    :key="att.id"
                                    @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                    class="flex items-center gap-3 px-4 py-3 bg-white border border-emerald-300 rounded-xl text-xs sm:text-sm text-emerald-950 hover:bg-emerald-50 hover:border-emerald-400 transition-all cursor-pointer shadow-xs group"
                                >
                                    <div class="w-9 h-9 rounded-lg bg-emerald-100 overflow-hidden shrink-0 flex items-center justify-center border border-emerald-200">
                                        <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                    </div>
                                    <span class="truncate max-w-[200px] font-semibold group-hover:text-emerald-700">{{ att.file_name }}</span>
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
                        <div class="flex items-center justify-between">
                            <InputLabel value="Foto Bukti Perbaikan" />
                            <span class="text-xs text-slate-400 font-normal italic">(Opsional)</span>
                        </div>
                        <p class="text-xs text-slate-500 mb-1.5">Unggah foto hasil perbaikan jika diperlukan (maksimal 3 foto).</p>
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
                    class="fixed inset-y-0 right-0 z-50 w-full md:w-1/2 bg-white shadow-2xl flex flex-col border-l border-slate-200"
                >
                    <!-- Drawer Header -->
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-100 flex items-center justify-center text-kominfo-primary">
                                <MessageSquare class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Panel Diskusi & Riwayat</h3>
                                <p class="text-xs text-slate-500 font-mono font-medium">{{ ticket.ticket_number }}</p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            @click="isDrawerOpen = false" 
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors"
                            title="Tutup Panel"
                        >
                            <XCircle class="w-6 h-6" />
                        </button>
                    </div>

                    <!-- Drawer Tabs Header -->
                    <div class="border-b border-slate-200 bg-white px-6 shrink-0">
                        <div class="flex space-x-6">
                            <button
                                type="button"
                                @click="activeTab = 'discussion'"
                                :class="[
                                    activeTab === 'discussion'
                                        ? 'border-kominfo-primary text-kominfo-primary font-bold'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                                    'inline-flex items-center gap-2 py-3.5 px-1 border-b-2 text-sm transition-all focus:outline-none'
                                ]"
                            >
                                <MessageSquare class="w-4 h-4" />
                                <span>Diskusi & Tanggapan</span>
                                <span 
                                    :class="[
                                        activeTab === 'discussion'
                                            ? 'bg-blue-100 text-kominfo-primary'
                                            : 'bg-slate-100 text-slate-600',
                                        'py-0.5 px-2 rounded-full text-xs font-semibold'
                                    ]"
                                >
                                    {{ ticketReplies.length }}
                                </span>
                            </button>

                            <button
                                type="button"
                                @click="activeTab = 'history'"
                                :class="[
                                    activeTab === 'history'
                                        ? 'border-kominfo-primary text-kominfo-primary font-bold'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                                    'inline-flex items-center gap-2 py-3.5 px-1 border-b-2 text-sm transition-all focus:outline-none'
                                ]"
                            >
                                <History class="w-4 h-4" />
                                <span>Riwayat Status</span>
                                <span 
                                    :class="[
                                        activeTab === 'history'
                                            ? 'bg-blue-100 text-kominfo-primary'
                                            : 'bg-slate-100 text-slate-600',
                                        'py-0.5 px-2 rounded-full text-xs font-semibold'
                                    ]"
                                >
                                    {{ ticket.status_histories.length }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Drawer Content: Discussion -->
                    <div v-show="activeTab === 'discussion'" class="flex-1 flex flex-col min-h-0 bg-slate-50/60">
                        <!-- Messages List -->
                        <div ref="discussionScrollContainer" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4">
                            <div v-if="ticketReplies.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-4">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3 border border-slate-200">
                                    <MessageSquare class="w-6 h-6" />
                                </div>
                                <p class="text-sm font-semibold text-slate-700">Belum Ada Diskusi</p>
                                <p class="text-xs text-slate-400 mt-1 max-w-xs">Gunakan kotak di bawah untuk berdiskusi, memberikan instruksi, atau memperbarui informasi tiket ini.</p>
                            </div>

                            <div 
                                v-for="reply in ticketReplies" 
                                :key="reply.id" 
                                class="flex w-full"
                                :class="Number(reply.user_id) === Number(currentUser.id) ? 'justify-end' : 'justify-start'"
                            >
                                <div 
                                    class="rounded-xl border transition-all shadow-xs overflow-hidden w-fit min-w-[200px] max-w-[92%] sm:max-w-[85%] md:max-w-[80%] lg:max-w-[72%]"
                                    :class="[
                                        reply.is_internal 
                                            ? 'bg-amber-50/70 border-amber-300' 
                                            : Number(reply.user_id) === Number(currentUser.id)
                                                ? 'bg-blue-50/70 border-blue-200/90 shadow-2xs' 
                                                : 'bg-white border-slate-200'
                                     ]"
                                >
                                    <!-- Internal Note Header Banner (If Internal) -->
                                    <div v-if="reply.is_internal" class="bg-amber-100/70 px-4 py-1.5 border-b border-amber-200/80 flex items-center justify-between gap-3 text-amber-900 text-xs font-semibold">
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <Lock class="w-3.5 h-3.5 text-amber-700" />
                                            <span>Catatan Internal</span>
                                        </div>
                                        <span class="text-[11px] font-normal text-amber-800 shrink-0">Hanya Admin & Teknisi</span>
                                    </div>

                                    <div class="p-3.5 space-y-2.5">
                                        <!-- Sender Info & Timestamp Stack (Compact without avatar) -->
                                        <div>
                                            <div class="flex flex-wrap items-center gap-1.5 leading-tight">
                                                <span class="font-bold text-sm text-slate-900 break-words">
                                                    {{ reply.user.name }}
                                                </span>

                                                <span 
                                                    class="inline-flex items-center text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full border shadow-2xs shrink-0"
                                                    :class="getRoleBadgeInfo(reply.user.role).badgeClass"
                                                >
                                                    {{ getRoleBadgeInfo(reply.user.role).label }}
                                                </span>

                                                <span 
                                                    v-if="Number(reply.user_id) === Number(currentUser.id)" 
                                                    class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-100 text-kominfo-primary border border-blue-200 shrink-0"
                                                >
                                                    Anda
                                                </span>
                                            </div>

                                            <!-- Timestamp placed neatly right below the sender info -->
                                            <div class="flex items-center gap-1 text-xs text-slate-400 font-mono mt-1">
                                                <Clock class="w-3 h-3 text-slate-400 shrink-0" />
                                                <span class="truncate">{{ formatDate(reply.created_at) }}</span>
                                            </div>
                                        </div>

                                        <!-- Message Body Content (Responsive with word wrapping) -->
                                        <div 
                                            class="text-sm leading-relaxed whitespace-pre-wrap break-words [overflow-wrap:anywhere]"
                                            :class="reply.is_internal ? 'text-amber-950' : 'text-slate-800'"
                                        >
                                            {{ reply.message }}
                                        </div>

                                        <!-- Reply Attachments Cards -->
                                        <div v-if="reply.attachments && reply.attachments.length > 0" class="pt-0.5">
                                            <div class="flex flex-wrap gap-2">
                                                <button 
                                                    type="button"
                                                    v-for="(att, attIdx) in reply.attachments" 
                                                    :key="att.id" 
                                                    @click="openImagePreview(reply.attachments.map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), attIdx)"
                                                    class="group flex items-center gap-2 bg-white border border-slate-200 hover:border-kominfo-primary/60 hover:bg-blue-50/40 rounded-lg p-1.5 pr-2.5 text-xs transition-all shadow-2xs cursor-pointer max-w-full"
                                                >
                                                    <div class="w-8 h-8 rounded-md bg-slate-100 overflow-hidden shrink-0 border border-slate-200">
                                                        <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" />
                                                    </div>
                                                    <div class="text-left min-w-0">
                                                        <p class="truncate max-w-[130px] font-semibold text-slate-700 group-hover:text-kominfo-primary">{{ att.file_name }}</p>
                                                        <span class="text-[10px] text-slate-400 group-hover:text-kominfo-primary/80">Lihat Lampiran</span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Reply Form at the Bottom of Drawer -->
                        <div v-if="canReply" class="p-4 bg-white border-t border-slate-200 shrink-0 shadow-sm">
                            <form @submit.prevent="submitReply" class="space-y-3">
                                <div class="relative">
                                    <Textarea 
                                        v-model="replyForm.message" 
                                        placeholder="Tulis pesan atau update progres..." 
                                        class="min-h-[85px] text-sm resize-y bg-slate-50/50 hover:bg-white focus:bg-white border-slate-200 transition-colors"
                                    />
                                </div>
                                <InputError :message="replyForm.errors.message" class="text-xs" />
                                <InputError :message="replyForm.errors.attachments" class="text-xs" />

                                <!-- Selected Attachments Chips -->
                                <div v-if="replyForm.attachments.length > 0" class="flex flex-wrap gap-2 pt-0.5">
                                    <div 
                                        v-for="(file, idx) in replyForm.attachments" 
                                        :key="idx"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-slate-700 text-xs shadow-2xs"
                                    >
                                        <Paperclip class="w-3.5 h-3.5 text-slate-400" />
                                        <span class="max-w-[140px] truncate font-medium">{{ file.name }}</span>
                                        <button 
                                            type="button" 
                                            @click="removeReplyAttachment(idx)" 
                                            class="text-slate-400 hover:text-rose-500 rounded p-0.5"
                                        >
                                            <XCircle class="w-3.5 h-3.5" />
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

                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <button 
                                            type="button" 
                                            @click="triggerReplyFileSelect"
                                            class="inline-flex items-center gap-1.5 text-xs text-slate-600 hover:text-kominfo-primary px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-white transition-colors"
                                            :disabled="replyForm.attachments.length >= 3"
                                        >
                                            <Paperclip class="w-3.5 h-3.5" />
                                            <span>Lampirkan</span>
                                            <span class="text-[11px] text-slate-400">({{ replyForm.attachments.length }}/3)</span>
                                        </button>

                                        <div v-if="['admin', 'technician'].includes(role)" class="flex items-center space-x-1.5">
                                            <Checkbox id="drawer_is_internal" v-model:checked="replyForm.is_internal" />
                                            <label for="drawer_is_internal" class="text-xs font-semibold text-amber-800 cursor-pointer select-none flex items-center gap-1">
                                                <Lock class="w-3 h-3 text-amber-700" />
                                                <span>Catatan Internal</span>
                                            </label>
                                        </div>
                                    </div>

                                    <Button type="submit" size="default" :disabled="replyForm.processing || (!replyForm.message && replyForm.attachments.length === 0)" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white px-4 h-9 text-xs sm:text-sm font-semibold">
                                        <Send class="w-3.5 h-3.5 mr-1.5" /> Kirim
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div v-else-if="['closed', 'cancelled'].includes(ticket.status)" class="p-4 bg-slate-100 border-t border-slate-200 text-center text-xs sm:text-sm text-slate-600 shrink-0">
                            Sesi diskusi telah ditutup ({{ ticket.status === 'closed' ? 'Closed' : 'Cancelled' }}).
                        </div>
                    </div>

                    <!-- Drawer Content: History Timeline -->
                    <div v-show="activeTab === 'history'" class="flex-1 overflow-y-auto p-6 bg-white">
                        <div v-if="ticket.status_histories.length === 0" class="text-center text-sm text-slate-400 py-14">
                            Belum ada catatan riwayat status.
                        </div>
                        <div v-else class="relative border-l-2 border-slate-200 ml-3 space-y-6">
                            <div v-for="history in ticket.status_histories" :key="history.id" class="relative pl-5 text-sm">
                                <div class="absolute w-3 h-3 bg-kominfo-primary rounded-full -left-[7px] top-1 border-2 border-white"></div>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2.5">
                                        <StatusBadge type="ticket" :status="history.new_status" class="text-xs" />
                                        <span class="text-xs text-slate-400 font-mono">{{ formatDate(history.created_at) }}</span>
                                    </div>
                                    <p class="text-sm text-slate-800 leading-relaxed font-normal">{{ history.comment }}</p>
                                    <p class="text-xs text-slate-400">Oleh: <span class="font-semibold text-slate-700">{{ history.changer?.name || 'Sistem' }}</span></p>
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
