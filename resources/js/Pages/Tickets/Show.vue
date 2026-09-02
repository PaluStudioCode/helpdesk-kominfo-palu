<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import FileUpload from '@/Components/FileUpload.vue';
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { 
    Clock, 
    User, 
    Building2, 
    Paperclip, 
    History,
    MessageSquare,
    CheckCircle2,
    RotateCcw,
    XCircle,
    Send,
    ArrowLeft,
    Shield,
    ShieldCheck,
    Lock,
    Users,
    Star,
    AlertTriangle,
    Info,
    Check,
    FileText,
    Cable,
    Network,
    Wifi
} from 'lucide-vue-next';
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
    ticket: any;
    categoriesMap?: Record<string, Array<{id: number, name: string, network_type: string}>>;
    technicians?: Array<{id: number, name: string, phone_number?: string}>;
    initialUnreadCount?: number;
}>();

const currentUser = computed(() => usePage().props.auth.user as any);
const role = computed(() => currentUser.value?.role);

// Permissions computed directly from Policies
const isDepartmentMatch = computed(() => {
    if (!currentUser.value?.department_id || !props.ticket.department_id) return false;
    return Number(currentUser.value.department_id) === Number(props.ticket.department_id);
});

const isAssignedTechnician = computed(() => {
    if (!currentUser.value) return false;
    if (Number(props.ticket.assigned_to) === Number(currentUser.value.id)) return true;
    if (props.ticket.technicians && props.ticket.technicians.some((t: any) => Number(t.id) === Number(currentUser.value.id))) return true;
    return false;
});

// 72 Hours Resubmit Constraint
const isWithin72Hours = computed(() => {
    if (props.ticket.status !== 'cancelled') return false;
    const cancelledTime = props.ticket.cancelled_at || props.ticket.updated_at;
    if (!cancelledTime) return false;
    const diffHours = (Date.now() - new Date(cancelledTime).getTime()) / (1000 * 60 * 60);
    return diffHours < 72;
});

const remainingResubmitHours = computed(() => {
    if (props.ticket.status !== 'cancelled') return 0;
    const cancelledTime = props.ticket.cancelled_at || props.ticket.updated_at;
    if (!cancelledTime) return 0;
    const diffHours = 72 - ((Date.now() - new Date(cancelledTime).getTime()) / (1000 * 60 * 60));
    return Math.max(0, Math.floor(diffHours));
});

// Action Permissions
const canVerifyAndAssign = computed(() => props.ticket.status === 'pending_admin' && role.value === 'admin');
const canReject = computed(() => props.ticket.status === 'pending_admin' && role.value === 'admin');
const canResubmit = computed(() => props.ticket.status === 'cancelled' && role.value === 'opd_user' && isDepartmentMatch.value && isWithin72Hours.value);
const canSubmitResolution = computed(() => props.ticket.status === 'in_progress' && (role.value === 'admin' || (role.value === 'technician' && isAssignedTechnician.value)));
const canApproveResolution = computed(() => props.ticket.status === 'pending_approval' && role.value === 'admin');
const canRequestRevision = computed(() => props.ticket.status === 'pending_approval' && role.value === 'admin');
const canRate = computed(() => props.ticket.status === 'closed' && role.value === 'opd_user' && isDepartmentMatch.value && props.ticket.rating === null);
const canReply = computed(() => !['closed', 'cancelled'].includes(props.ticket.status) && (['admin', 'technician'].includes(role.value) || (role.value === 'opd_user' && isDepartmentMatch.value)));

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    }) + ' WITA';
};

// Text color helpers (Clean formal typography without bg badge)
const getStatusLabel = (status: string) => {
    switch (status) {
        case 'pending_admin': return 'Menunggu Verifikasi';
        case 'in_progress': return 'Sedang Dikerjakan';
        case 'pending_approval': return 'Menunggu Review';
        case 'closed': return 'Selesai';
        case 'cancelled': return 'Ditolak';
        default: return status || '-';
    }
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'pending_admin': return 'text-blue-600 font-semibold';
        case 'in_progress': return 'text-amber-600 font-semibold';
        case 'pending_approval': return 'text-purple-600 font-semibold';
        case 'closed': return 'text-emerald-600 font-semibold';
        case 'cancelled': return 'text-rose-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const getPriorityLabel = (priority: string) => {
    switch (priority) {
        case 'low': return 'Rendah';
        case 'medium': return 'Sedang';
        case 'high': return 'Tinggi';
        case 'emergency': return 'Darurat';
        default: return priority || '-';
    }
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'low': return 'text-slate-500 font-semibold';
        case 'medium': return 'text-blue-600 font-semibold';
        case 'high': return 'text-amber-600 font-semibold';
        case 'emergency': return 'text-rose-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const getNetworkLabel = (type: string) => {
    switch (type) {
        case 'fiber_optic': return 'Fiber Optic';
        case 'lan': return 'LAN';
        case 'wifi': return 'WiFi';
        default: return type || '-';
    }
};

const getNetworkColor = (type: string) => {
    switch (type) {
        case 'fiber_optic': return 'text-purple-600 font-semibold';
        case 'lan': return 'text-cyan-600 font-semibold';
        case 'wifi': return 'text-sky-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const getRoleLabel = (userRole: string) => {
    switch (userRole) {
        case 'admin': return 'Administrator';
        case 'technician': return 'Teknisi';
        case 'opd_user': return 'Pelapor OPD';
        default: return userRole || 'User';
    }
};

const getRoleColor = (userRole: string) => {
    switch (userRole) {
        case 'admin': return 'text-purple-600 font-semibold';
        case 'technician': return 'text-amber-600 font-semibold';
        case 'opd_user': return 'text-blue-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const getSlaStatus = (ticket: any) => {
    if (ticket.status === 'closed') {
        return {
            status: 'completed',
            label: '✓ Kendala selesai ditangani',
            textColor: 'text-emerald-700 font-medium'
        };
    }

    if (ticket.status === 'cancelled') {
        return {
            status: 'cancelled',
            label: '• Tiket ditolak',
            textColor: 'text-slate-500'
        };
    }

    if (ticket.status === 'pending_admin') {
        return {
            status: 'pending_admin',
            label: '⏱ SLA ditangguhkan (Menunggu Verifikasi)',
            textColor: 'text-blue-700 font-medium'
        };
    }

    if (!ticket.due_at) return null;
    
    const now = new Date();
    const dueAt = new Date(ticket.due_at);
    const diffHours = (dueAt.getTime() - now.getTime()) / (1000 * 60 * 60);

    if (diffHours < 0) {
        return { 
            status: 'danger', 
            label: '⚠️ Melewati batas target SLA',
            textColor: 'text-rose-600 font-semibold'
        };
    }
    if (diffHours <= 2) {
        const remainingMinutes = Math.max(1, Math.round(diffHours * 60));
        return { 
            status: 'warning', 
            label: `⏳ Mendekati SLA (sisa ${remainingMinutes} menit)`,
            textColor: 'text-amber-600 font-semibold'
        };
    }
    return { 
        status: 'safe', 
        label: '✓ Dalam batas SLA',
        textColor: 'text-emerald-600'
    };
};

// Modals State
const isVerifyModalOpen = ref(false);
const isRejectModalOpen = ref(false);
const isResubmitModalOpen = ref(false);
const isResolutionModalOpen = ref(false);
const isApproveModalOpen = ref(false);
const isRevisionModalOpen = ref(false);
const isDrawerOpen = ref(false);

// Active Tab in Feed
const activeTab = ref<'discussion' | 'history'>('discussion');

// Image Preview Gallery
const previewModalOpen = ref(false);
const previewImages = ref<Array<{ url: string; name: string }>>([]);
const previewInitialIndex = ref(0);

const openImagePreview = (imagesList: Array<{ url: string; name: string }>, index: number = 0) => {
    previewImages.value = imagesList;
    previewInitialIndex.value = index;
    previewModalOpen.value = true;
};

// 1. Verify & Assign Form (Admin)
const verifyForm = useForm({
    network_type: props.ticket.network_type || 'lan',
    category_id: props.ticket.category_id ? props.ticket.category_id.toString() : '',
    priority: props.ticket.priority || 'medium',
    technician_ids: [] as number[],
});

const verifyCategories = computed(() => {
    if (!verifyForm.network_type || !props.categoriesMap) return [];
    return props.categoriesMap[verifyForm.network_type] || [];
});

const toggleVerifyTechnician = (techId: number) => {
    const idx = verifyForm.technician_ids.indexOf(techId);
    if (idx === -1) verifyForm.technician_ids.push(techId);
    else verifyForm.technician_ids.splice(idx, 1);
};

const submitVerifyAndAssign = () => {
    verifyForm.post(route('tickets.verify-assign', props.ticket.id), {
        onSuccess: () => {
            isVerifyModalOpen.value = false;
        }
    });
};

// 2. Reject Form (Admin)
const rejectForm = useForm({
    reason: '',
});

const submitReject = () => {
    rejectForm.post(route('tickets.reject', props.ticket.id), {
        onSuccess: () => {
            isRejectModalOpen.value = false;
            rejectForm.reset();
        }
    });
};

// 3. Resubmit Form (OPD)
const resubmitForm = useForm({
    title: props.ticket.title || '',
    location_details: props.ticket.location_details || '',
    description: props.ticket.description || '',
    attachments: [] as File[],
});

const submitResubmit = () => {
    resubmitForm.post(route('tickets.resubmit', props.ticket.id), {
        onSuccess: () => {
            isResubmitModalOpen.value = false;
            resubmitForm.reset();
        }
    });
};

// 4. Submit Resolution Form (Technician)
const resolutionForm = useForm({
    resolution_note: '',
    network_type: props.ticket.network_type || 'lan',
    category_id: props.ticket.category_id ? props.ticket.category_id.toString() : '',
    resolution_proofs: [] as File[],
});

const resolutionCategories = computed(() => {
    if (!resolutionForm.network_type || !props.categoriesMap) return [];
    return props.categoriesMap[resolutionForm.network_type] || [];
});

const submitResolution = () => {
    resolutionForm.post(route('tickets.submit-resolution', props.ticket.id), {
        onSuccess: () => {
            isResolutionModalOpen.value = false;
            resolutionForm.reset();
        }
    });
};

// 5. Approve Resolution Form (Admin)
const approveForm = useForm({});
const submitApprove = () => {
    approveForm.post(route('tickets.approve-resolution', props.ticket.id), {
        onSuccess: () => {
            isApproveModalOpen.value = false;
        }
    });
};

// 6. Request Revision Form (Admin)
const revisionForm = useForm({
    comment: '',
});
const submitRevision = () => {
    revisionForm.post(route('tickets.request-revision', props.ticket.id), {
        onSuccess: () => {
            isRevisionModalOpen.value = false;
            revisionForm.reset();
        }
    });
};

// 7. Rating Form (OPD on Closed Ticket)
const ratingHover = ref(0);
const ratingForm = useForm({
    rating: 5,
    feedback_comment: '',
});
const submitRating = () => {
    ratingForm.post(route('tickets.rate', props.ticket.id));
};

// 8. Reply Form
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

    const validFiles: File[] = [];
    const maxSizeBytes = 5 * 1024 * 1024;
    const allowedExts = ['jpg', 'jpeg', 'png'];

    for (const file of selected.slice(0, Math.max(0, availableSlots))) {
        const ext = file.name.split('.').pop()?.toLowerCase() || '';
        if (!file.type.startsWith('image/') && !allowedExts.includes(ext)) {
            replyForm.setError('attachments', `Format gambar tidak didukung (gunakan JPG, JPEG, PNG).`);
            continue;
        }
        if (file.size > maxSizeBytes) {
            replyForm.setError('attachments', `Ukuran gambar "${file.name}" melebihi 5 MB.`);
            continue;
        }
        validFiles.push(file);
    }

    if (validFiles.length > 0) {
        replyForm.attachments = [...replyForm.attachments, ...validFiles];
    }
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
const unreadRepliesCount = ref(props.initialUnreadCount ?? 0);

watch(() => props.initialUnreadCount, (newVal) => {
    unreadRepliesCount.value = newVal ?? 0;
});

const markThreadAsRead = () => {
    unreadRepliesCount.value = 0;
    axios.post(route('tickets.mark-read', props.ticket.id)).catch(() => {});
};

// Filtered Replies: OPD User never sees internal notes
const visibleReplies = computed(() => {
    if (role.value === 'opd_user') {
        return ticketReplies.value.filter(r => !r.is_internal);
    }
    return ticketReplies.value;
});

// Typing Indicator State
const typingUsers = ref<Record<number, { name: string; role: string; is_internal?: boolean; timeout: any }>>({});

const activeTypingText = computed(() => {
    const list = Object.values(typingUsers.value).filter(u => {
        if (role.value === 'opd_user' && u.is_internal) return false;
        return true;
    });
    if (list.length === 0) return '';
    if (list.length === 1) {
        const u = list[0];
        const roleLabel = u.role === 'admin' ? ' (Admin)' : u.role === 'technician' ? ' (Teknisi)' : ' (OPD)';
        return `${u.name}${roleLabel} sedang mengetik...`;
    }
    return `${list.length} orang sedang mengetik...`;
});

let typingThrottleTimeout: any = null;
const handleTyping = () => {
    if (!echoChannel) return;
    if (!typingThrottleTimeout) {
        const payload = {
            id: currentUser.value?.id,
            name: currentUser.value?.name,
            role: currentUser.value?.role,
            is_internal: Boolean(replyForm.is_internal),
        };

        if (replyForm.is_internal && echoInternalChannel) {
            echoInternalChannel.whisper('typing', payload);
        } else if (echoChannel) {
            echoChannel.whisper('typing', payload);
        }

        typingThrottleTimeout = setTimeout(() => {
            typingThrottleTimeout = null;
        }, 2000);
    }
};

const onUserTyping = (data: any) => {
    if (!data || Number(data.id) === Number(currentUser.value?.id)) return;
    // OPD user never sees typing indicator for internal notes
    if (role.value === 'opd_user' && data.is_internal) return;
    
    if (typingUsers.value[data.id]?.timeout) {
        clearTimeout(typingUsers.value[data.id].timeout);
    }

    const timeout = setTimeout(() => {
        delete typingUsers.value[data.id];
    }, 3000);

    typingUsers.value[data.id] = {
        name: data.name,
        role: data.role,
        is_internal: Boolean(data.is_internal),
        timeout
    };
};

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

watch([isDrawerOpen, activeTab], ([drawerOpen, tab]) => {
    if (drawerOpen && tab === 'discussion') {
        markThreadAsRead();
        scrollToBottom();
    }
});

let echoChannel: any = null;
let echoInternalChannel: any = null;

onMounted(() => {
    if (typeof window !== 'undefined' && (window as any).Echo) {
        // 1. Subscribe to Public Channel (OPD, Admin, Technicians)
        echoChannel = (window as any).Echo.private(`ticket.${props.ticket.id}`);

        // Listen for new public replies
        echoChannel.listen('.reply.created', (event: any) => {
            const newReply = event.reply;
            // Strict security gate: Ignore internal replies if received on public channel for OPD
            if (role.value === 'opd_user' && newReply.is_internal) {
                return;
            }
            const exists = ticketReplies.value.some((r: any) => r.id === newReply.id);
            if (!exists) {
                ticketReplies.value.push(newReply);
                scrollToBottom();
                if (!isDrawerOpen.value || activeTab.value !== 'discussion') {
                    unreadRepliesCount.value++;
                } else {
                    markThreadAsRead();
                }
            }
        });

        // Listen for realtime ticket status & history updates
        echoChannel.listen('.status.updated', (event: any) => {
            if (event.ticket) {
                Object.assign(props.ticket, event.ticket);
            }
            if (event.new_history) {
                const historyExists = props.ticket.status_histories.some((h: any) => h.id === event.new_history.id);
                if (!historyExists) {
                    props.ticket.status_histories.unshift(event.new_history);
                }
            }
        });

        // Listen for typing whispers on public channel
        echoChannel.listenForWhisper('typing', onUserTyping);

        // 2. Subscribe to Internal Channel (Admin & Technician ONLY)
        if (['admin', 'technician'].includes(role.value)) {
            echoInternalChannel = (window as any).Echo.private(`ticket.${props.ticket.id}.internal`);

            echoInternalChannel.listen('.reply.created', (event: any) => {
                const newReply = event.reply;
                const exists = ticketReplies.value.some((r: any) => r.id === newReply.id);
                if (!exists) {
                    ticketReplies.value.push(newReply);
                    scrollToBottom();
                    if (!isDrawerOpen.value || activeTab.value !== 'discussion') {
                        unreadRepliesCount.value++;
                    } else {
                        markThreadAsRead();
                    }
                }
            });

            echoInternalChannel.listenForWhisper('typing', onUserTyping);
        }
    }
});

onUnmounted(() => {
    if (typeof window !== 'undefined' && (window as any).Echo) {
        (window as any).Echo.leave(`ticket.${props.ticket.id}`);
        if (['admin', 'technician'].includes(role.value)) {
            (window as any).Echo.leave(`ticket.${props.ticket.id}.internal`);
        }
    }
    echoChannel = null;
    echoInternalChannel = null;
    
    // Clear all typing timeouts
    Object.values(typingUsers.value).forEach(u => clearTimeout(u.timeout));
    typingUsers.value = {};
});
</script>

<template>
    <Head :title="`Tiket ${ticket.ticket_number}`" />

    <AuthenticatedLayout>
        <div class="max-w-6xl mx-auto space-y-5">
            
            <!-- Ticket Action Bar (Formal, Rapi, Dimensi Stabil) -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 sm:px-6 py-3.5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 min-h-[64px]">
                <!-- Kiri: Tombol Kembali + Nomor Tiket + Status -->
                <div class="flex items-center gap-3 min-w-0">
                    <Link 
                        :href="route('tickets.index')" 
                        class="h-9 w-9 flex items-center justify-center rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 hover:text-slate-900 transition-colors shrink-0" 
                        title="Kembali ke Antrean"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div class="flex items-center gap-2.5 min-w-0 flex-wrap">
                        <span class="text-lg sm:text-xl font-bold text-slate-900 font-mono tracking-tight shrink-0">{{ ticket.ticket_number }}</span>
                        <span class="text-slate-300 font-light hidden sm:inline">•</span>
                        <div class="flex items-center gap-1.5 text-xs sm:text-sm shrink-0">
                            <span class="text-slate-400 font-medium">Status:</span>
                            <span :class="getStatusColor(ticket.status)">
                                {{ getStatusLabel(ticket.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Kanan: Action Buttons (Selalu Flex & Ukuran Konsisten) -->
                <div class="flex items-center gap-2 shrink-0 flex-nowrap overflow-x-auto pb-1 sm:pb-0">
                    <!-- Tombol Diskusi & Riwayat -->
                    <Button 
                        @click="isDrawerOpen = true" 
                        size="sm" 
                        variant="outline" 
                        class="h-9 px-3.5 border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs sm:text-sm font-medium relative whitespace-nowrap"
                    >
                        <MessageSquare class="w-4 h-4 mr-1.5 text-kominfo-primary" />
                        <span>Diskusi & Riwayat</span>
                        <span v-if="unreadRepliesCount > 0" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center shadow-xs animate-bounce">
                            {{ unreadRepliesCount }}
                        </span>
                    </Button>

                    <!-- Admin Actions: Pending Admin -->
                    <Button 
                        v-if="canVerifyAndAssign" 
                        @click="isVerifyModalOpen = true" 
                        size="sm" 
                        class="h-9 px-3.5 bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <ShieldCheck class="w-4 h-4 mr-1.5" /> Verifikasi & Tugaskan
                    </Button>

                    <Button 
                        v-if="canReject" 
                        @click="isRejectModalOpen = true" 
                        size="sm" 
                        variant="destructive" 
                        class="h-9 px-3.5 text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <XCircle class="w-4 h-4 mr-1.5" /> Tolak Laporan
                    </Button>

                    <!-- Technician Action: In Progress -->
                    <Button 
                        v-if="canSubmitResolution" 
                        @click="isResolutionModalOpen = true" 
                        size="sm" 
                        class="h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <CheckCircle2 class="w-4 h-4 mr-1.5" /> Selesaikan Perbaikan
                    </Button>

                    <!-- Admin Actions: Pending Approval (Quality Gate) -->
                    <Button 
                        v-if="canApproveResolution" 
                        @click="isApproveModalOpen = true" 
                        size="sm" 
                        class="h-9 px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <CheckCircle2 class="w-4 h-4 mr-1.5" /> Setujui Hasil Kerja
                    </Button>

                    <Button 
                        v-if="canRequestRevision" 
                        @click="isRevisionModalOpen = true" 
                        size="sm" 
                        variant="outline" 
                        class="h-9 px-3.5 border-amber-300 text-amber-900 bg-amber-50/50 hover:bg-amber-100 text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <RotateCcw class="w-4 h-4 mr-1.5 text-amber-700" /> Minta Revisi
                    </Button>

                    <!-- OPD Action: Resubmit on Cancelled (within 72 hours) -->
                    <Button 
                        v-if="canResubmit" 
                        @click="isResubmitModalOpen = true" 
                        size="sm" 
                        class="h-9 px-3.5 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <RotateCcw class="w-4 h-4 mr-1.5" /> Perbaiki & Ajukan Kembali
                    </Button>
                </div>
            </div>

            <!-- Contextual Workflow Status Banners -->
            
            <!-- 1. Cancelled / Rejection Banner (OPD Resubmit Grace Period) -->
            <div v-if="ticket.status === 'cancelled'" class="p-5 bg-rose-50 border border-rose-200 rounded-xl space-y-3">
                <div class="flex items-start gap-3">
                    <AlertTriangle class="w-6 h-6 text-rose-600 shrink-0 mt-0.5" />
                    <div class="space-y-1 text-sm text-rose-900 flex-1">
                        <p class="font-bold text-rose-950">Laporan Tiket Ditolak oleh Admin Diskominfo</p>
                        <p class="text-rose-800 leading-relaxed">
                            Laporan tidak dapat diproses lebih lanjut pada kondisi saat ini.
                        </p>
                        <div v-if="isWithin72Hours" class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-rose-200 mt-2">
                            <span class="text-xs font-semibold text-rose-900 flex items-center gap-1.5">
                                <Clock class="w-4 h-4 text-rose-600" />
                                Masa perbaikan laporan aktif: Tersisa {{ remainingResubmitHours }} jam
                            </span>
                            <Button v-if="canResubmit" @click="isResubmitModalOpen = true" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white text-xs">
                                Perbaiki Laporan Sekarang
                            </Button>
                        </div>
                        <div v-else class="text-xs text-rose-700 pt-1 font-medium italic">
                            Masa perbaikan (72 jam) telah berakhir. Harap daftarkan laporan baru jika kendala masih terjadi.
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Pending Admin Banner -->
            <div v-if="ticket.status === 'pending_admin'" class="p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3 text-sm text-blue-900">
                <Info class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-blue-950">Menunggu Verifikasi & Disposisi Admin</p>
                    <p class="text-blue-800 text-xs sm:text-sm mt-0.5 leading-relaxed">
                        Laporan telah diterima dan sedang menunggu validasi kelayakan serta penugasan Tim Teknisi oleh Administrator Diskominfo.
                    </p>
                </div>
            </div>

            <!-- 3. Pending Approval Banner -->
            <div v-if="ticket.status === 'pending_approval'" class="p-4 bg-purple-50 border border-purple-200 rounded-xl flex items-start gap-3 text-sm text-purple-900">
                <ShieldCheck class="w-5 h-5 text-purple-600 shrink-0 mt-0.5" />
                <div>
                    <p class="font-bold text-purple-950">Pekerjaan Lapangan Selesai (Menunggu Review Mutu Admin)</p>
                    <p class="text-purple-800 text-xs sm:text-sm mt-0.5 leading-relaxed">
                        Tim Teknisi telah menyelesaikan perbaikan di lokasi dan mengunggah dokumentasi solusi. Menunggu peninjauan kendali mutu oleh Administrator.
                    </p>
                </div>
            </div>

            <!-- Main Ticket Docket Card -->
            <Card class="border-slate-200 shadow-xs bg-white overflow-hidden rounded-xl">
                <!-- Header Kartu: Judul Kendala -->
                <div class="p-6 sm:p-7 border-b border-slate-100 bg-slate-50/40">
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight leading-snug">
                        {{ ticket.title }}
                    </h1>
                </div>

                <!-- Main Content Body -->
                <CardContent class="p-6 sm:p-7 space-y-8">
                    <!-- SEKSI 1: INFORMASI PENGADUAN KENDALA (Pelapor OPD) -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <FileText class="w-4 h-4 text-slate-400" /> Informasi Pengaduan Kendala
                        </h3>

                        <!-- Grid Data Pengaduan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-lg bg-slate-50/60 border border-slate-200/80 mb-5">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Instansi (OPD)</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ ticket.department.name }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Lokasi / Ruangan</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ ticket.location_details || '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Nama Pelapor</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ ticket.reporter.name }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Pengajuan</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ formatDate(ticket.created_at) }}</p>
                            </div>
                        </div>

                        <!-- Rincian Deskripsi Masalah -->
                        <div>
                            <p class="text-xs font-semibold text-slate-700 mb-1.5">Deskripsi Gangguan:</p>
                            <div class="text-slate-800 text-sm leading-relaxed whitespace-pre-wrap bg-white p-4 rounded-lg border border-slate-200">
                                {{ ticket.description }}
                            </div>
                        </div>

                        <!-- Foto Bukti Gangguan Awal -->
                        <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').length > 0" class="mt-4">
                            <p class="text-xs font-semibold text-slate-700 mb-2.5">Foto Bukti Gangguan Awal:</p>
                            <div class="flex flex-wrap gap-3">
                                <button 
                                    type="button"
                                    v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof')" 
                                    :key="att.id"
                                    @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                    class="flex items-center gap-3 px-3.5 py-2.5 border border-slate-200 rounded-lg hover:border-blue-500 hover:bg-blue-50/40 bg-white text-xs text-slate-800 transition-all shadow-xs group cursor-pointer"
                                >
                                    <div class="w-8 h-8 rounded bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                                        <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                    </div>
                                    <span class="truncate max-w-[200px] font-medium group-hover:text-blue-600">{{ att.file_name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 2: DISPOSISI & SPESIFIKASI TEKNIS (Diskominfo) -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <Shield class="w-4 h-4 text-slate-400" /> Disposisi & Parameter Penanganan Teknis
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-lg bg-slate-50/60 border border-slate-200/80">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Infrastruktur & Kategori</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">
                                    {{ ticket.network_type ? getNetworkLabel(ticket.network_type) : '-' }}
                                </p>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ ticket.category ? ticket.category.name : 'Belum diverifikasi' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tingkat Prioritas</p>
                                <p class="text-sm mt-0.5 font-bold" :class="getPriorityColor(ticket.priority)">
                                    {{ getPriorityLabel(ticket.priority) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Target Waktu SLA</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">
                                    {{ ticket.due_at ? formatDate(ticket.due_at) : '-' }}
                                </p>
                                <p 
                                    v-if="getSlaStatus(ticket)" 
                                    class="text-xs mt-0.5 font-medium"
                                    :class="getSlaStatus(ticket)?.textColor"
                                >
                                    {{ getSlaStatus(ticket)?.label }}
                                </p>
                            </div>

                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tim Teknisi Lapangan</p>
                                <div v-if="ticket.technicians && ticket.technicians.length > 0" class="flex flex-wrap gap-1.5 mt-1">
                                    <span 
                                        v-for="tech in ticket.technicians" 
                                        :key="tech.id"
                                        class="text-xs font-semibold text-slate-800 bg-white border border-slate-200 px-2 py-0.5 rounded"
                                    >
                                        {{ tech.name }}
                                    </span>
                                </div>
                                <div v-else-if="ticket.assignee" class="mt-0.5">
                                    <p class="font-bold text-slate-900 text-sm">{{ ticket.assignee.name }}</p>
                                </div>
                                <p v-else class="text-xs text-slate-400 italic mt-0.5">
                                    Belum ditugaskan
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 3: CATATAN SOLUSI & BUKTI PERBAIKAN (Jika ada) -->
                    <div v-if="ticket.resolution_note">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-slate-400" /> Hasil Penanganan & Solusi Teknis
                        </h3>

                        <!-- Grid Data Penyelesaian -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 rounded-lg bg-slate-50/60 border border-slate-200/80 mb-5">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Pengerjaan</p>
                                <p class="font-bold text-emerald-600 text-sm mt-0.5">Selesai Ditangani</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Penyelesaian</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ ticket.resolved_at ? formatDate(ticket.resolved_at) : '-' }}</p>
                            </div>
                        </div>

                        <!-- Rincian Solusi Teknis -->
                        <div>
                            <p class="text-xs font-semibold text-slate-700 mb-1.5">Tindakan Solusi Teknis:</p>
                            <div class="text-slate-800 text-sm leading-relaxed whitespace-pre-wrap bg-white p-4 rounded-lg border border-slate-200">
                                {{ ticket.resolution_note }}
                            </div>
                        </div>

                        <!-- Foto Bukti Solusi Perbaikan -->
                        <div v-if="ticket.attachments && ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').length > 0" class="mt-4">
                            <p class="text-xs font-semibold text-slate-700 mb-2.5">Foto Bukti Hasil Perbaikan:</p>
                            <div class="flex flex-wrap gap-3">
                                <button 
                                    type="button"
                                    v-for="(att, idx) in ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof')" 
                                    :key="att.id"
                                    @click="openImagePreview(ticket.attachments.filter((a: any) => a.attachment_type === 'resolution_proof').map((a: any) => ({ url: `/storage/${a.file_path}`, name: a.file_name })), idx)"
                                    class="flex items-center gap-3 px-3.5 py-2.5 border border-slate-200 rounded-lg hover:border-emerald-500 hover:bg-emerald-50/40 bg-white text-xs text-slate-800 transition-all shadow-xs group cursor-pointer"
                                >
                                    <div class="w-8 h-8 rounded bg-slate-100 overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                                        <img :src="`/storage/${att.file_path}`" :alt="att.file_name" class="w-full h-full object-cover" />
                                    </div>
                                    <span class="truncate max-w-[200px] font-medium group-hover:text-emerald-700">{{ att.file_name }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SEKSI 4: EVALUASI KEPUASAN LAYANAN (CSAT) (Bila status Closed) -->
                    <div v-if="ticket.status === 'closed'">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <Star class="w-4 h-4 text-slate-400" /> Evaluasi Kepuasan Layanan (CSAT)
                        </h3>

                        <!-- If already rated -->
                        <div v-if="ticket.rating" class="p-4 rounded-lg bg-slate-50/60 border border-slate-200/80 space-y-3">
                            <div class="flex items-center justify-between flex-wrap gap-2">
                                <div class="flex items-center gap-1.5">
                                    <Star 
                                        v-for="star in 5" 
                                        :key="star" 
                                        class="w-5 h-5"
                                        :class="star <= ticket.rating ? 'text-amber-500 fill-amber-500' : 'text-slate-300'"
                                    />
                                    <span class="ml-2 font-bold text-slate-800 text-sm">({{ ticket.rating }} / 5 Bintang)</span>
                                </div>
                                <span v-if="ticket.rated_at" class="text-xs text-slate-400">
                                    Dinilai pada: {{ formatDate(ticket.rated_at) }}
                                </span>
                            </div>
                            <p v-if="ticket.feedback_comment" class="text-sm text-slate-700 italic bg-white p-3.5 rounded-lg border border-slate-200">
                                "{{ ticket.feedback_comment }}"
                            </p>
                        </div>

                        <!-- If not rated yet and user can rate -->
                        <div v-else-if="canRate" class="p-5 rounded-lg bg-slate-50/60 border border-slate-200/80">
                            <form @submit.prevent="submitRating" class="space-y-4">
                                <p class="text-xs text-slate-700 leading-relaxed">
                                    Mohon berikan penilaian atas kecepatan dan kualitas layanan perbaikan tim teknisi Diskominfo Palu:
                                </p>

                                <!-- Interactive Star Selection -->
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        v-for="star in 5"
                                        :key="star"
                                        @mouseenter="ratingHover = star"
                                        @mouseleave="ratingHover = 0"
                                        @click="ratingForm.rating = star"
                                        class="p-1 hover:scale-110 transition-transform focus:outline-none cursor-pointer"
                                    >
                                        <Star 
                                            class="w-7 h-7 transition-colors"
                                            :class="(ratingHover ? star <= ratingHover : star <= ratingForm.rating) ? 'text-amber-500 fill-amber-500' : 'text-slate-300'"
                                        />
                                    </button>
                                    <span class="font-bold text-slate-800 text-sm ml-2">
                                        {{ ratingForm.rating }} Bintang
                                    </span>
                                </div>

                                <div>
                                    <InputLabel for="feedback_comment" value="Ulasan atau Catatan Tambahan (Opsional)" class="text-slate-700 text-xs font-medium" />
                                    <Textarea 
                                        id="feedback_comment"
                                        v-model="ratingForm.feedback_comment"
                                        placeholder="Tuliskan pengalaman atau saran Anda terhadap penanganan tiket ini..."
                                        rows="2"
                                        class="bg-white mt-1 text-sm border-slate-200"
                                    />
                                </div>

                                <Button type="submit" :disabled="ratingForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white text-xs font-semibold">
                                    {{ ratingForm.processing ? 'Menyimpan...' : 'Kirim Penilaian Kepuasan' }}
                                </Button>
                            </form>
                        </div>

                        <div v-else class="text-xs text-slate-400 italic">
                            Penilaian belum diberikan oleh pihak pelapor OPD.
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ================= MODALS ================= -->

        <!-- 1. Verify & Assign Modal (Admin) -->
        <Dialog v-model:open="isVerifyModalOpen">
            <DialogContent class="sm:max-w-[620px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Verifikasi & Disposisi Tim Teknisi</DialogTitle>
                    <DialogDescription>
                        Validasi kelayakan laporan gangguan, tetapkan jenis infrastruktur jaringan, kategori dugaan awal, dan pilih Tim Teknisi penanggung jawab.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitVerifyAndAssign" class="space-y-4">
                    <!-- Network Type -->
                    <div>
                        <InputLabel value="Jenis Infrastruktur Jaringan *" class="text-xs font-medium mb-1" />
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="verifyForm.network_type = 'fiber_optic'; verifyForm.category_id = ''"
                                :class="[
                                    'flex flex-col items-center justify-center p-2.5 rounded-lg border text-xs font-medium transition-all',
                                    verifyForm.network_type === 'fiber_optic' ? 'border-kominfo-primary bg-blue-50 ring-2 ring-kominfo-primary/20 text-kominfo-primary' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <Cable class="w-5 h-5 mb-1 text-purple-600" />
                                <span>Fiber Optic</span>
                            </button>
                            <button
                                type="button"
                                @click="verifyForm.network_type = 'lan'; verifyForm.category_id = ''"
                                :class="[
                                    'flex flex-col items-center justify-center p-2.5 rounded-lg border text-xs font-medium transition-all',
                                    verifyForm.network_type === 'lan' ? 'border-kominfo-primary bg-blue-50 ring-2 ring-kominfo-primary/20 text-kominfo-primary' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <Network class="w-5 h-5 mb-1 text-cyan-600" />
                                <span>LAN</span>
                            </button>
                            <button
                                type="button"
                                @click="verifyForm.network_type = 'wifi'; verifyForm.category_id = ''"
                                :class="[
                                    'flex flex-col items-center justify-center p-2.5 rounded-lg border text-xs font-medium transition-all',
                                    verifyForm.network_type === 'wifi' ? 'border-kominfo-primary bg-blue-50 ring-2 ring-kominfo-primary/20 text-kominfo-primary' : 'border-slate-200 hover:bg-slate-50'
                                ]"
                            >
                                <Wifi class="w-5 h-5 mb-1 text-sky-600" />
                                <span>WiFi</span>
                            </button>
                        </div>
                    </div>

                    <!-- Category & Priority Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="verify_category_id" value="Kategori Masalah / Dugaan Awal *" class="text-xs font-medium" />
                            <Select v-model="verifyForm.category_id">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Pilih Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="cat in verifyCategories" :key="cat.id" :value="cat.id.toString()">
                                        {{ cat.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="verifyForm.errors.category_id" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="verify_priority" value="Tingkat Prioritas *" class="text-xs font-medium" />
                            <Select v-model="verifyForm.priority">
                                <SelectTrigger class="mt-1">
                                    <SelectValue placeholder="Pilih Prioritas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Rendah (Low)</SelectItem>
                                    <SelectItem value="medium">Sedang (Medium)</SelectItem>
                                    <SelectItem value="high">Tinggi (High)</SelectItem>
                                    <SelectItem value="emergency">Darurat (Emergency)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="verifyForm.errors.priority" class="mt-1" />
                        </div>
                    </div>

                    <!-- Multi-Technician Checkboxes -->
                    <div>
                        <InputLabel value="Pilih Tim Teknisi Penanggung Jawab *" class="text-xs font-medium mb-1" />
                        <div class="border border-slate-200 rounded-lg p-3 max-h-36 overflow-y-auto space-y-2 bg-slate-50/50">
                            <label 
                                v-for="tech in technicians" 
                                :key="tech.id"
                                class="flex items-center gap-2 text-xs text-slate-800 cursor-pointer hover:bg-white p-1.5 rounded transition-colors"
                            >
                                <input 
                                    type="checkbox" 
                                    :checked="verifyForm.technician_ids.includes(tech.id)"
                                    @change="toggleVerifyTechnician(tech.id)"
                                    class="rounded border-slate-300 text-kominfo-primary focus:ring-kominfo-primary w-4 h-4"
                                />
                                <span class="font-semibold">{{ tech.name }}</span>
                                <span v-if="tech.phone_number" class="text-slate-400 font-mono text-[10px]">({{ tech.phone_number }})</span>
                            </label>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Dapat memilih lebih dari 1 personil untuk penugasan tim kolaboratif.</p>
                        <InputError :message="verifyForm.errors.technician_ids" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isVerifyModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="verifyForm.processing || verifyForm.technician_ids.length === 0 || !verifyForm.category_id" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ verifyForm.processing ? 'Memproses...' : 'Setujui & Tugaskan Tim' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 2. Reject Modal (Admin) -->
        <Dialog v-model:open="isRejectModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Tolak Laporan Tiket</DialogTitle>
                    <DialogDescription>
                        Tuliskan alasan penolakan secara jelas. Pihak OPD akan menerima notifikasi dan diberikan waktu 72 jam untuk memperbaiki data laporan.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitReject" class="space-y-4">
                    <div>
                        <InputLabel for="reject_reason" value="Alasan Penolakan *" class="text-xs font-medium" />
                        <Textarea 
                            id="reject_reason"
                            v-model="rejectForm.reason"
                            placeholder="Cth: Foto bukti buram / rincian lokasi ruangan tidak jelas / bukan kewenangan jaringan Kominfo..."
                            rows="4"
                            class="mt-1 text-sm bg-white"
                            required
                        />
                        <InputError :message="rejectForm.errors.reason" class="mt-1" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isRejectModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="rejectForm.processing || !rejectForm.reason" variant="destructive">
                            {{ rejectForm.processing ? 'Memproses...' : 'Ya, Tolak Laporan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 3. Resubmit Modal (OPD) -->
        <Dialog v-model:open="isResubmitModalOpen">
            <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Perbaiki & Ajukan Kembali Laporan</DialogTitle>
                    <DialogDescription>
                        Perbarui detail lokasi, deskripsi kendala, atau unggah foto bukti baru agar dapat diverifikasi ulang oleh Admin Diskominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitResubmit" class="space-y-4">
                    <div>
                        <InputLabel for="resubmit_title" value="Subjek / Ringkasan Kendala *" class="text-xs font-medium" />
                        <Input id="resubmit_title" v-model="resubmitForm.title" class="mt-1" />
                        <InputError :message="resubmitForm.errors.title" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="resubmit_location" value="Lokasi Detail / Ruangan *" class="text-xs font-medium" />
                        <Input id="resubmit_location" v-model="resubmitForm.location_details" class="mt-1" />
                        <InputError :message="resubmitForm.errors.location_details" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="resubmit_description" value="Deskripsi Lengkap Kendala *" class="text-xs font-medium" />
                        <Textarea id="resubmit_description" v-model="resubmitForm.description" rows="3" class="mt-1 text-sm" />
                        <InputError :message="resubmitForm.errors.description" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Unggah Foto Bukti Baru" class="text-xs font-medium mb-1" />
                        <FileUpload 
                            v-model="resubmitForm.attachments"
                            :multiple="true"
                            :maxFiles="3"
                            :maxSizeMB="5"
                            @error="(msg) => resubmitForm.errors.attachments = msg"
                        />
                        <InputError :message="resubmitForm.errors.attachments" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isResubmitModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="resubmitForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            {{ resubmitForm.processing ? 'Mengirim...' : 'Ajukan Kembali Laporan' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 4. Submit Resolution Modal (Technician) -->
        <Dialog v-model:open="isResolutionModalOpen">
            <DialogContent class="sm:max-w-[580px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Lapor Selesai & Konfirmasi Kategori Riil</DialogTitle>
                    <DialogDescription>
                        Isi catatan tindakan perbaikan lapangan dan konfirmasi jika ada perubahan jenis jaringan atau kategori gangguan sebenarnya di lokasi.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitResolution" class="space-y-4">
                    <!-- Confirm Real Category (Optional Correction) -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                        <div class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Konfirmasi Temuan Lapangan Sebenarnya
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Jenis Jaringan Riil" class="text-xs font-medium" />
                                <Select v-model="resolutionForm.network_type">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Jaringan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="fiber_optic">Fiber Optic</SelectItem>
                                        <SelectItem value="lan">LAN</SelectItem>
                                        <SelectItem value="wifi">WiFi</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <InputLabel value="Kategori Masalah Riil" class="text-xs font-medium" />
                                <Select v-model="resolutionForm.category_id">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Kategori Riil" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="cat in resolutionCategories" :key="cat.id" :value="cat.id.toString()">
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500">Perubahan kategori riil akan mengalkulasi ulang target SLA otomatis.</p>
                    </div>

                    <!-- Resolution Note -->
                    <div>
                        <InputLabel for="resolution_note" value="Catatan Solusi Teknis Perbaikan *" class="text-xs font-medium" />
                        <Textarea 
                            id="resolution_note"
                            v-model="resolutionForm.resolution_note"
                            placeholder="Cth: Mengganti konektor RJ45 yang korosi dan mengonfigurasi VLAN port switch..."
                            rows="4"
                            class="mt-1 text-sm bg-white"
                            required
                        />
                        <InputError :message="resolutionForm.errors.resolution_note" class="mt-1" />
                    </div>

                    <!-- Resolution Proof Photos -->
                    <div>
                        <InputLabel value="Foto Bukti Hasil Perbaikan" class="text-xs font-medium mb-1" />
                        <FileUpload 
                            v-model="resolutionForm.resolution_proofs"
                            :multiple="true"
                            :maxFiles="3"
                            :maxSizeMB="5"
                            @error="(msg) => resolutionForm.errors.resolution_proofs = msg"
                        />
                        <InputError :message="resolutionForm.errors.resolution_proofs" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isResolutionModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="resolutionForm.processing || !resolutionForm.resolution_note" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            {{ resolutionForm.processing ? 'Mengirim...' : 'Kirim untuk Review Mutu' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 5. Approve Resolution Modal (Admin) -->
        <Dialog v-model:open="isApproveModalOpen">
            <DialogContent class="sm:max-w-[440px]">
                <DialogHeader>
                    <DialogTitle>Setujui Hasil Kerja & Tutup Tiket</DialogTitle>
                    <DialogDescription>
                        Apakah Anda telah memeriksa catatan solusi dan bukti foto dari tim teknisi? Tiket ini akan resmi berstatus Closed (Selesai).
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitApprove">
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isApproveModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="approveForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            {{ approveForm.processing ? 'Memproses...' : 'Ya, Setujui & Tutup Tiket' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- 6. Request Revision Modal (Admin) -->
        <Dialog v-model:open="isRevisionModalOpen">
            <DialogContent class="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle>Minta Perbaikan Ulang (Revisi)</DialogTitle>
                    <DialogDescription>
                        Tiket akan dikembalikan ke status In Progress. Tuliskan instruksi hal-hal yang perlu disempurnakan oleh Tim Teknisi.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitRevision" class="space-y-4">
                    <div>
                        <InputLabel for="revision_comment" value="Catatan Instruksi Revisi *" class="text-xs font-medium" />
                        <Textarea 
                            id="revision_comment"
                            v-model="revisionForm.comment"
                            placeholder="Cth: Lampirkan foto speedtest setelah penggantian kabel atau periksa koneksi di port lainnya..."
                            rows="4"
                            class="mt-1 text-sm bg-white"
                            required
                        />
                        <InputError :message="revisionForm.errors.comment" class="mt-1" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isRevisionModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="revisionForm.processing || !revisionForm.comment" class="bg-amber-600 hover:bg-amber-700 text-white">
                            {{ revisionForm.processing ? 'Mengirim...' : 'Kirim Instruksi Revisi' }}
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
                                    {{ visibleReplies.length }}
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
                            <div v-if="visibleReplies.length === 0" class="flex flex-col items-center justify-center py-16 text-center px-4">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3 border border-slate-200">
                                    <MessageSquare class="w-6 h-6" />
                                </div>
                                <p class="text-sm font-semibold text-slate-700">Belum Ada Diskusi</p>
                                <p class="text-xs text-slate-400 mt-1 max-w-xs">Gunakan kotak di bawah untuk berdiskusi atau koordinasi penanganan tiket ini.</p>
                            </div>

                            <div 
                                v-for="reply in visibleReplies" 
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
                                    <!-- Internal Note Header Banner -->
                                    <div v-if="reply.is_internal" class="bg-amber-100/70 px-4 py-1.5 border-b border-amber-200/80 flex items-center justify-between gap-3 text-amber-900 text-xs font-semibold">
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <Lock class="w-3.5 h-3.5 text-amber-700" />
                                            <span>Catatan Internal</span>
                                        </div>
                                        <span class="text-[11px] font-normal text-amber-800 shrink-0">Hanya Admin & Teknisi</span>
                                    </div>

                                    <div class="p-3.5 space-y-2.5">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-1.5 leading-tight">
                                                <span class="font-bold text-sm text-slate-900 break-words">
                                                    {{ reply.user.name }}
                                                </span>

                                                <span class="text-slate-300 font-light">•</span>

                                                <span 
                                                    class="text-xs font-semibold"
                                                    :class="getRoleColor(reply.user.role)"
                                                >
                                                    {{ getRoleLabel(reply.user.role) }}
                                                </span>

                                                <span 
                                                    v-if="Number(reply.user_id) === Number(currentUser.id)" 
                                                    class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 shrink-0"
                                                >
                                                    Anda
                                                </span>
                                            </div>

                                            <div class="flex items-center gap-1 text-xs text-slate-400 font-mono mt-1">
                                                <Clock class="w-3 h-3 text-slate-400 shrink-0" />
                                                <span class="truncate">{{ formatDate(reply.created_at) }}</span>
                                            </div>
                                        </div>

                                        <div 
                                            class="text-sm leading-relaxed whitespace-pre-wrap break-words [overflow-wrap:anywhere]"
                                            :class="reply.is_internal ? 'text-amber-950' : 'text-slate-800'"
                                        >
                                            {{ reply.message }}
                                        </div>

                                        <!-- Reply Attachments -->
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
                                                        <span class="text-[10px] text-slate-400 group-hover:text-kominfo-primary/80">Lihat Foto</span>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Typing Indicator Banner -->
                        <div v-if="activeTypingText" class="px-4 py-1.5 bg-blue-50/80 border-t border-blue-100 text-slate-600 text-xs flex items-center gap-2 shrink-0 animate-fade-in">
                            <div class="flex space-x-1 items-center">
                                <span class="w-1.5 h-1.5 bg-kominfo-primary rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                                <span class="w-1.5 h-1.5 bg-kominfo-primary rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                                <span class="w-1.5 h-1.5 bg-kominfo-primary rounded-full animate-bounce"></span>
                            </div>
                            <span class="font-medium text-slate-700 truncate">{{ activeTypingText }}</span>
                        </div>

                        <!-- Sticky Reply Form -->
                        <div v-if="canReply" class="p-4 bg-white border-t border-slate-200 shrink-0 shadow-sm space-y-3">
                            <!-- Internal Note Active Visual Banner -->
                            <div v-if="replyForm.is_internal" class="p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-900 text-xs flex items-center gap-2 animate-fade-in">
                                <Lock class="w-4 h-4 text-amber-700 shrink-0" />
                                <span class="font-semibold">Mode Catatan Internal: Pesan ini hanya dapat dibaca oleh Admin & Tim Teknisi.</span>
                            </div>

                            <form @submit.prevent="submitReply" class="space-y-3">
                                <div class="relative">
                                    <Textarea 
                                        v-model="replyForm.message" 
                                        @input="handleTyping"
                                        :placeholder="replyForm.is_internal ? 'Tulis catatan internal untuk koordinasi tim teknis...' : 'Tulis pesan tanggapan atau koordinasi penanganan...'" 
                                        class="min-h-[85px] text-sm resize-y transition-colors"
                                        :class="replyForm.is_internal ? 'bg-amber-50/40 border-amber-300 focus:border-amber-500 focus:ring-amber-500 text-amber-950 placeholder:text-amber-700/60' : 'bg-slate-50/50 hover:bg-white focus:bg-white border-slate-200'"
                                    />
                                </div>
                                <InputError :message="replyForm.errors.message" class="text-xs" />
                                <InputError :message="replyForm.errors.attachments" class="text-xs" />

                                <!-- Selected Attachments -->
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
                                            <Checkbox id="drawer_is_internal" v-model="replyForm.is_internal" />
                                            <label for="drawer_is_internal" class="text-xs font-semibold text-amber-800 cursor-pointer select-none flex items-center gap-1">
                                                <Lock class="w-3 h-3 text-amber-700" />
                                                <span>Catatan Internal</span>
                                            </label>
                                        </div>
                                    </div>

                                    <Button 
                                        type="submit" 
                                        size="default" 
                                        :disabled="replyForm.processing || (!replyForm.message && replyForm.attachments.length === 0)" 
                                        :class="replyForm.is_internal ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-kominfo-primary hover:bg-kominfo-primary-dark text-white'"
                                        class="px-4 h-9 text-xs sm:text-sm font-semibold transition-colors"
                                    >
                                        <Lock v-if="replyForm.is_internal" class="w-3.5 h-3.5 mr-1.5" />
                                        <Send v-else class="w-3.5 h-3.5 mr-1.5" />
                                        {{ replyForm.is_internal ? 'Kirim Catatan Internal' : 'Kirim' }}
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <div v-else-if="['closed', 'cancelled'].includes(ticket.status)" class="p-4 bg-slate-100 border-t border-slate-200 text-center text-xs sm:text-sm text-slate-600 shrink-0">
                            Sesi diskusi telah ditutup ({{ ticket.status === 'closed' ? 'Selesai' : 'Ditolak' }}).
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
                                        <span :class="getStatusColor(history.new_status)" class="text-xs font-semibold">
                                            {{ getStatusLabel(history.new_status) }}
                                        </span>
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
