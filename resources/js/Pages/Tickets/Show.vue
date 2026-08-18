<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import FileUpload from '@/Components/FileUpload.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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
    XCircle
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
const canAssign = computed(() => ['open'].includes(props.ticket.status) && ['admin', 'technician'].includes(role.value));
const canResolve = computed(() => props.ticket.status === 'in_progress' && (role.value === 'admin' || (role.value === 'technician' && props.ticket.assigned_to === currentUser.value.id)));
const canClose = computed(() => props.ticket.status === 'resolved' && (role.value === 'admin' || role.value === 'opd_user'));
const canReopen = computed(() => props.ticket.status === 'resolved' && (role.value === 'admin' || role.value === 'opd_user'));
const canCancel = computed(() => (role.value === 'admin' && ['open', 'in_progress'].includes(props.ticket.status)) || (role.value === 'opd_user' && props.ticket.status === 'open'));
const canReply = computed(() => !['closed', 'cancelled'].includes(props.ticket.status));

const formatDate = (dateStr: string) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const getSlaStatus = (ticket: any) => {
    if (!ticket.due_at || ['resolved', 'closed', 'cancelled'].includes(ticket.status)) return null;
    
    const now = new Date();
    const dueAt = new Date(ticket.due_at);
    const diffHours = (dueAt.getTime() - now.getTime()) / (1000 * 60 * 60);

    if (diffHours < 0) return { status: 'danger', label: 'Overdue SLA' };
    if (diffHours <= 2) return { status: 'warning', label: 'Mendekati SLA' };
    return { status: 'safe', label: 'SLA Aman' };
};

// Modals State
const isAssignModalOpen = ref(false);
const isResolveModalOpen = ref(false);
const isCloseModalOpen = ref(false);
const isReopenModalOpen = ref(false);
const isCancelModalOpen = ref(false);

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
        onSuccess: () => isAssignModalOpen.value = false
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
const replyForm = useForm({
    message: '',
    is_internal: false,
    attachments: [] as File[]
});

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
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl font-bold text-slate-800">{{ ticket.ticket_number }}</span>
                    <StatusBadge type="ticket" :status="ticket.status" />
                    <StatusBadge v-if="getSlaStatus(ticket)" type="sla" :status="getSlaStatus(ticket).status" />
                </div>
                <Link :href="route('tickets.index')">
                    <Button variant="outline" size="sm">Kembali ke Antrean</Button>
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto space-y-6">
            <div v-if="$page.props.flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md p-4 mb-2">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash.error" class="bg-red-50 border border-red-200 text-red-800 rounded-md p-4 mb-2">
                {{ $page.props.flash.error }}
            </div>

            <!-- Quick Action Bar -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-slate-200 flex flex-wrap gap-3 items-center" v-if="canAssign || canResolve || canClose || canReopen || canCancel">
                <span class="text-sm font-medium text-slate-600 mr-2">Aksi Cepat:</span>
                
                <Button v-if="canAssign" @click="isAssignModalOpen = true" class="bg-blue-600 hover:bg-blue-700">
                    <UserCheck class="w-4 h-4 mr-2" /> Ambil / Tugaskan Tiket
                </Button>
                
                <Button v-if="canResolve" @click="isResolveModalOpen = true" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                    <CheckCircle2 class="w-4 h-4 mr-2" /> Selesaikan Tiket
                </Button>

                <Button v-if="canClose" @click="isCloseModalOpen = true" class="bg-slate-800 hover:bg-slate-900 text-white">
                    <CheckCircle2 class="w-4 h-4 mr-2" /> Konfirmasi & Selesai (Close)
                </Button>

                <Button v-if="canReopen" @click="isReopenModalOpen = true" variant="outline" class="border-amber-300 text-amber-800 hover:bg-amber-50">
                    <RotateCcw class="w-4 h-4 mr-2" /> Buka Kembali Tiket (Reopen)
                </Button>

                <Button v-if="canCancel" @click="isCancelModalOpen = true" variant="destructive">
                    <XCircle class="w-4 h-4 mr-2" /> Batalkan Tiket
                </Button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Col 1 & 2: Main Info & Chat -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Incident Info Panel -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-xl">{{ ticket.title }}</CardTitle>
                            <div class="flex items-center gap-2 mt-2">
                                <StatusBadge type="network" :status="ticket.network_type" />
                                <StatusBadge type="priority" :status="ticket.priority" />
                                <span class="text-xs text-slate-500 flex items-center"><Clock class="w-3 h-3 mr-1"/> {{ formatDate(ticket.created_at) }}</span>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-lg border border-slate-100">
                                <div>
                                    <span class="text-xs font-medium text-slate-500 flex items-center mb-1"><Building2 class="w-3 h-3 mr-1"/> Instansi / OPD</span>
                                    <div class="text-sm font-semibold text-slate-800">{{ ticket.department.name }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-slate-500 flex items-center mb-1"><User class="w-3 h-3 mr-1"/> Pelapor</span>
                                    <div class="text-sm font-semibold text-slate-800">{{ ticket.reporter.name }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-slate-500 flex items-center mb-1"><MapPin class="w-3 h-3 mr-1"/> Lokasi Spesifik</span>
                                    <div class="text-sm font-semibold text-slate-800">{{ ticket.location_details }}</div>
                                </div>
                                <div>
                                    <span class="text-xs font-medium text-slate-500 flex items-center mb-1"><AlertCircle class="w-3 h-3 mr-1"/> Kategori Gangguan</span>
                                    <div class="text-sm font-semibold text-slate-800">{{ ticket.category.name }}</div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900 mb-2">Deskripsi Masalah:</h4>
                                <div class="bg-white p-4 rounded border border-slate-200 text-slate-700 whitespace-pre-wrap text-sm leading-relaxed">
                                    {{ ticket.description }}
                                </div>
                            </div>

                            <!-- Resolution Note if Resolved/Closed -->
                            <div v-if="ticket.resolution_note" class="bg-emerald-50/70 border border-emerald-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-emerald-900 mb-2 flex items-center">
                                    <CheckCircle2 class="w-4 h-4 mr-1 text-emerald-600"/> Catatan Solusi Perbaikan (Teknisi):
                                </h4>
                                <p class="text-sm text-emerald-800 whitespace-pre-wrap leading-relaxed">{{ ticket.resolution_note }}</p>
                                <p v-if="ticket.resolved_at" class="text-xs text-emerald-600 mt-2">Diselesaikan pada: {{ formatDate(ticket.resolved_at) }}</p>
                            </div>

                            <!-- Issue Proofs -->
                            <div v-if="ticket.attachments && ticket.attachments.length > 0">
                                <h4 class="text-sm font-semibold text-slate-900 mb-2 flex items-center"><Paperclip class="w-4 h-4 mr-1"/> Lampiran Berkas / Bukti:</h4>
                                <div class="flex flex-wrap gap-3">
                                    <a 
                                        v-for="att in ticket.attachments.filter((a: any) => a.attachment_type === 'issue_proof' || a.attachment_type === 'resolution_proof')" 
                                        :key="att.id"
                                        :href="`/storage/${att.file_path}`" 
                                        target="_blank"
                                        class="flex items-center gap-2 p-2 border border-slate-200 rounded hover:bg-slate-50 transition-colors"
                                    >
                                        <div class="w-10 h-10 bg-slate-100 rounded overflow-hidden flex justify-center items-center shrink-0">
                                            <img v-if="att.file_path.match(/\.(jpeg|jpg|png)$/i)" :src="`/storage/${att.file_path}`" class="object-cover w-full h-full" />
                                            <Paperclip v-else class="w-4 h-4 text-slate-500" />
                                        </div>
                                        <div class="text-xs max-w-[150px]">
                                            <p class="truncate font-medium text-kominfo-primary">{{ att.file_name }}</p>
                                            <span class="text-[10px] uppercase font-bold text-slate-400">{{ att.attachment_type === 'resolution_proof' ? 'Bukti Perbaikan' : 'Bukti Gangguan' }}</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Thread Percakapan -->
                    <Card>
                        <CardHeader class="pb-3 border-b border-slate-100">
                            <CardTitle class="text-lg flex items-center gap-2"><MessageSquare class="w-5 h-5"/> Thread Diskusi & Catatan</CardTitle>
                        </CardHeader>
                        <CardContent class="pt-6 space-y-6">
                            
                            <!-- Messages Loop -->
                            <div v-if="ticket.replies.length === 0" class="text-center py-6 text-slate-500 text-sm">
                                Belum ada tanggapan atau catatan.
                            </div>
                            
                            <div v-for="reply in ticket.replies" :key="reply.id" 
                                class="flex gap-4 p-4 rounded-xl border"
                                :class="[
                                    reply.is_internal ? 'bg-amber-50/50 border-amber-200' : 
                                    reply.user_id === currentUser.id ? 'bg-blue-50/30 border-blue-100 ml-8' : 'bg-slate-50/50 border-slate-200 mr-8'
                                ]"
                            >
                                <div class="shrink-0 pt-1">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold"
                                        :class="reply.user.role === 'opd_user' ? 'bg-slate-500' : 'bg-kominfo-primary'"
                                    >
                                        {{ reply.user.name.substring(0, 2).toUpperCase() }}
                                    </div>
                                </div>
                                <div class="flex-1 overflow-hidden">
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-sm text-slate-900">{{ reply.user.name }}</span>
                                            <span v-if="reply.is_internal" class="text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 px-2 py-0.5 rounded">Catatan Internal</span>
                                        </div>
                                        <span class="text-xs text-slate-500">{{ formatDate(reply.created_at) }}</span>
                                    </div>
                                    <div class="text-sm text-slate-700 whitespace-pre-wrap">{{ reply.message }}</div>
                                    
                                    <!-- Reply Attachments -->
                                    <div v-if="reply.attachments && reply.attachments.length > 0" class="mt-3 flex flex-wrap gap-2">
                                        <a v-for="att in reply.attachments" :key="att.id" :href="`/storage/${att.file_path}`" target="_blank" class="text-xs bg-white border border-slate-200 rounded p-1.5 flex items-center gap-1 hover:border-kominfo-primary transition-colors">
                                            <Paperclip class="w-3 h-3 text-slate-400" />
                                            <span class="truncate max-w-[100px]">{{ att.file_name }}</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Reply Form -->
                            <div v-if="canReply" class="mt-8 border-t border-slate-200 pt-6">
                                <form @submit.prevent="submitReply" class="space-y-4">
                                    <div>
                                        <Textarea 
                                            v-model="replyForm.message" 
                                            placeholder="Tulis tanggapan, update progres, atau catatan..." 
                                            class="min-h-[100px] resize-y"
                                        />
                                        <InputError :message="replyForm.errors.message" class="mt-1" />
                                    </div>

                                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-medium text-slate-600">Lampirkan file opsional (Maks 3 file):</span>
                                        </div>
                                        <FileUpload 
                                            v-model="replyForm.attachments" 
                                            :multiple="true" 
                                            :max-files="3"
                                        />
                                    </div>

                                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                                        <div class="flex items-center gap-2">
                                            <div v-if="['admin', 'technician'].includes(role)" class="flex items-center space-x-2">
                                                <Checkbox id="is_internal" v-model:checked="replyForm.is_internal" />
                                                <label for="is_internal" class="text-sm font-medium leading-none text-slate-700 cursor-pointer">
                                                    Kirim sebagai Catatan Internal (Disembunyikan dari OPD)
                                                </label>
                                            </div>
                                        </div>
                                        <Button type="submit" :disabled="replyForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark shrink-0">
                                            Kirim Balasan
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </CardContent>
                    </Card>

                </div>

                <!-- Col 3: Side Panel (Status, Timeline) -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-sm font-semibold">Penanggung Jawab</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="ticket.assignee" class="flex items-center gap-3 bg-slate-50 p-3 rounded border border-slate-100">
                                <div class="w-10 h-10 rounded-full bg-kominfo-primary/10 flex items-center justify-center text-kominfo-primary font-bold">
                                    {{ ticket.assignee.name.substring(0, 2).toUpperCase() }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900">{{ ticket.assignee.name }}</p>
                                    <p class="text-xs text-slate-500">Teknisi Jaringan</p>
                                </div>
                            </div>
                            <div v-else class="text-sm text-amber-600 font-medium bg-amber-50 p-3 rounded border border-amber-200 flex items-center gap-2">
                                <AlertCircle class="w-4 h-4" /> Belum ditugaskan
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3 border-b border-slate-100">
                            <CardTitle class="text-sm font-semibold flex items-center gap-2"><History class="w-4 h-4"/> Timeline Status</CardTitle>
                        </CardHeader>
                        <CardContent class="pt-6">
                            <div class="relative border-l-2 border-slate-200 ml-3 space-y-6">
                                <div v-for="history in ticket.status_histories" :key="history.id" class="relative pl-6">
                                    <div class="absolute w-3 h-3 bg-slate-200 rounded-full -left-[7px] top-1.5 border-2 border-white"></div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <StatusBadge type="ticket" :status="history.new_status" />
                                            <span class="text-[10px] text-slate-400">{{ formatDate(history.created_at) }}</span>
                                        </div>
                                        <p class="text-xs text-slate-600 mt-1">{{ history.comment }}</p>
                                        <p class="text-[10px] font-medium text-slate-400">Oleh: {{ history.changer?.name || 'Sistem' }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Assign Modal -->
        <Dialog v-model:open="isAssignModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Klaim Penugasan Tiket</DialogTitle>
                    <DialogDescription>
                        Anda akan mengambil alih penanganan tiket ini. Status akan berubah menjadi In Progress.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitAssign">
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isAssignModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="assignForm.processing" class="bg-kominfo-primary">
                            Ya, Ambil Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Resolve Modal -->
        <Dialog v-model:open="isResolveModalOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Selesaikan Penanganan Tiket</DialogTitle>
                    <DialogDescription>
                        Tuliskan tindakan perbaikan yang telah dilakukan dan unggah foto bukti jika ada.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitResolve" class="space-y-4">
                    <div>
                        <InputLabel for="resolution_note" value="Catatan Solusi Perbaikan (Wajib, min 10 karakter)" />
                        <Textarea 
                            id="resolution_note"
                            v-model="resolveForm.resolution_note"
                            placeholder="Cth: Mengganti konektor RJ45 yang longgar dan mengetes ulang koneksi jaringan..."
                            class="min-h-[100px] mt-1"
                        />
                        <InputError :message="resolveForm.errors.resolution_note" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Foto Bukti Perbaikan (Opsional, Maks 3)" />
                        <FileUpload 
                            v-model="resolveForm.resolution_proofs"
                            :multiple="true"
                            :max-files="3"
                            accept="image/jpeg,image/png"
                        />
                        <InputError :message="resolveForm.errors.resolution_proofs" class="mt-1" />
                    </div>

                    <DialogFooter class="mt-6">
                        <Button type="button" variant="outline" @click="isResolveModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="resolveForm.processing" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            Tandai Selesai
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Close Modal (OPD Confirmation without mandatory comment form) -->
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
                        <Button type="submit" :disabled="closeForm.processing" class="bg-slate-900 text-white">
                            Ya, Konfirmasi & Tutup
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Reopen Modal -->
        <Dialog v-model:open="isReopenModalOpen">
            <DialogContent class="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Buka Kembali Tiket</DialogTitle>
                    <DialogDescription>
                        Tiket akan dikembalikan statusnya ke In Progress dan diteruskan ke teknisi penanggung jawab sebelumnya.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitReopen" class="space-y-4">
                    <div>
                        <InputLabel for="reopen_comment" value="Alasan Kendala Masih Terjadi" />
                        <Textarea 
                            id="reopen_comment"
                            v-model="reopenForm.comment"
                            placeholder="Jelaskan bagian mana yang masih belum berfungsi..."
                            class="mt-1"
                        />
                        <InputError :message="reopenForm.errors.comment" class="mt-1" />
                    </div>
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isReopenModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="reopenForm.processing" class="bg-amber-600 hover:bg-amber-700 text-white">
                            Buka Kembali Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cancel Modal -->
        <Dialog v-model:open="isCancelModalOpen">
            <DialogContent class="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Batalkan Laporan Tiket</DialogTitle>
                    <DialogDescription>
                        Tiket yang dibatalkan akan diarsipkan dan tidak lagi diproses. Masukkan alasan pembatalan.
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitCancel" class="space-y-4">
                    <div>
                        <InputLabel for="cancel_comment" value="Alasan Pembatalan (Wajib)" />
                        <Textarea 
                            id="cancel_comment"
                            v-model="cancelForm.comment"
                            placeholder="Cth: Laporan duplikat / kendala terselesaikan mandiri..."
                            class="mt-1"
                        />
                        <InputError :message="cancelForm.errors.comment" class="mt-1" />
                    </div>
                    <DialogFooter class="mt-4">
                        <Button type="button" variant="outline" @click="isCancelModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="cancelForm.processing" variant="destructive">
                            Ya, Batalkan Tiket
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
