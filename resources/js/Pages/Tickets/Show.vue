<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { 
    formatDateWithWita as formatDate,
    getStatusLabel,
    getStatusColor,
    getPriorityLabel,
    getPriorityColor,
    getNetworkLabel,
    getNetworkColor,
    getRoleLabel,
    getRoleColor
} from '@/lib/ticket-helpers';
import FileUpload from '@/Components/FileUpload.vue';
import ImagePreviewModal from '@/Components/ImagePreviewModal.vue';
import LiveTicketTimer from '@/Components/LiveTicketTimer.vue';
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
    Lock,
    Users,
    Star,
    AlertTriangle,
    Check,
    Cable,
    Network,
    Wifi,
    Zap,
    Repeat,
    Globe,
    Wrench,
    Activity,
    FileText,
    Search,
    Plus,
    Trash2,
    Boxes,
    PauseCircle,
    PlayCircle,
    Info
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
    categoriesMap?: Record<string, Array<{id: number, name: string, infrastructure_type?: string, network_type?: string}>>;
    technicians?: Array<{id: number, name: string, phone_number?: string}>;
    availableDevices?: string[];
    availableMaterials?: Array<{ name: string; default_unit: string }>;
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
const canCancelByReporter = computed(() => props.ticket.status === 'pending_admin' && role.value === 'opd_user' && isDepartmentMatch.value);
const canResubmit = computed(() => props.ticket.status === 'cancelled' && role.value === 'opd_user' && isDepartmentMatch.value && isWithin72Hours.value);
const canHold = computed(() => props.ticket.status === 'in_progress' && role.value === 'technician' && isAssignedTechnician.value);
const canResume = computed(() => props.ticket.status === 'on_hold' && role.value === 'technician' && isAssignedTechnician.value);
const canSubmitResolution = computed(() => props.ticket.status === 'in_progress' && role.value === 'technician' && isAssignedTechnician.value);
const canApproveResolution = computed(() => props.ticket.status === 'pending_approval' && role.value === 'admin');
const canRequestRevision = computed(() => props.ticket.status === 'pending_approval' && role.value === 'admin');
const canRate = computed(() => props.ticket.status === 'closed' && role.value === 'opd_user' && isDepartmentMatch.value && props.ticket.rating === null);
const canReply = computed(() => {
    if (['closed', 'cancelled'].includes(props.ticket.status)) return false;
    if (role.value === 'admin') return true;
    if (role.value === 'technician') return isAssignedTechnician.value;
    if (role.value === 'opd_user') return isDepartmentMatch.value;
    return false;
});

const cancellationInfo = computed(() => {
    if (props.ticket.status !== 'cancelled') return null;
    const cancelledHistory = props.ticket.status_histories?.find((h: any) => h.new_status === 'cancelled');
    const isCancelledByReporter = cancelledHistory?.comment?.startsWith('Dibatalkan oleh Pelapor');
    return {
        isCancelledByReporter,
        comment: cancelledHistory?.comment || 'Laporan telah dibatalkan / ditolak.',
    };
});

const getHoldCategoryLabel = (cat: string | null | undefined) => {
    const labels: Record<string, string> = {
        vendor_isp: 'Ketergantungan Pihak Ketiga (Vendor ISP / Telkom / PLN)',
        material_procurement: 'Ketiadaan Material & Suku Cadang (Menunggu Pengadaan)',
        access_permit: 'Kendala Izin Akses Fisik / Kunci Lokasi',
        weather_force_majeure: 'Faktor Keamanan & Cuaca Ekstrem',
        need_escalation: 'Eskalasi ke Tim Ahli / Network Engineer',
    };
    return cat ? (labels[cat] || cat) : '-';
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
            label: '• Tiket ditolak / dibatalkan',
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

    if (ticket.status === 'on_hold') {
        return {
            status: 'warning',
            label: '⏱ SLA dijeda sementara (Clock Paused)',
            textColor: 'text-amber-700 font-semibold'
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
const isCancelModalOpen = ref(false);
const isResubmitModalOpen = ref(false);
const isHoldModalOpen = ref(false);
const isResolutionModalOpen = ref(false);
const isApproveModalOpen = ref(false);
const isRevisionModalOpen = ref(false);
const isDrawerOpen = ref(false);

const holdReasonCategories = [
    { value: 'vendor_isp', label: 'Ketergantungan Pihak Ketiga (Vendor ISP / Telkom / PLN)' },
    { value: 'material_procurement', label: 'Ketiadaan Material & Suku Cadang (Menunggu Pengadaan)' },
    { value: 'access_permit', label: 'Kendala Izin Akses Fisik / Kunci Lokasi' },
    { value: 'weather_force_majeure', label: 'Faktor Keamanan & Cuaca Ekstrem' },
    { value: 'need_escalation', label: 'Eskalasi ke Tim Ahli / Network Engineer' },
];

const holdForm = useForm({
    hold_reason_category: 'vendor_isp',
    hold_reason_note: '',
});

const submitHold = () => {
    holdForm.post(route('tickets.hold', props.ticket.id), {
        onSuccess: () => {
            isHoldModalOpen.value = false;
            holdForm.reset();
        }
    });
};

const resumeForm = useForm({});

const submitResume = () => {
    resumeForm.post(route('tickets.resume', props.ticket.id));
};

const quickCancelReasons = [
    'Kendala jaringan telah teratasi sendiri oleh OPD',
    'Terdapat kesalahan input lokasi / deskripsi masalah',
    'Laporan ganda (sudah dilaporkan staf lain)',
    'Lainnya (Tuliskan alasan sendiri)',
];
const selectedQuickReason = ref<string>('');

const cancelForm = useForm({
    reason: '',
});

const selectQuickCancelReason = (option: string) => {
    selectedQuickReason.value = option;
    if (option === 'Lainnya (Tuliskan alasan sendiri)') {
        cancelForm.reason = '';
    } else {
        cancelForm.reason = option;
    }
};

const submitCancelByReporter = () => {
    cancelForm.post(route('tickets.cancel-by-reporter', props.ticket.id), {
        onSuccess: () => {
            isCancelModalOpen.value = false;
            cancelForm.reset();
            selectedQuickReason.value = '';
        }
    });
};

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
    priority: props.ticket.priority || 'medium',
    technician_ids: [] as number[],
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

const selectedAffectedDevice = ref<string>('');

const resolutionForm = useForm({
    affected_device: props.ticket.affected_device || '',
    actual_repair_location: props.ticket.actual_repair_location || props.ticket.location_details || '',
    infrastructure_type: props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic',
    network_type: props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic',
    category_id: props.ticket.category_id ? props.ticket.category_id.toString() : '',
    inspection_result: props.ticket.inspection_result || '',
    root_cause: props.ticket.root_cause || '',
    action_taken: props.ticket.action_taken || '',
    materials_used: props.ticket.materials_used || '',
    test_result: props.ticket.test_result || '',
    test_parameters: props.ticket.test_parameters || '',
    notes: props.ticket.resolution_note || '',
    resolution_proofs: [] as File[],
});

const syncAffectedDevice = () => {
    resolutionForm.affected_device = selectedAffectedDevice.value;
};

watch(selectedAffectedDevice, () => {
    syncAffectedDevice();
});

const resolutionCategories = computed(() => {
    const infraType = resolutionForm.infrastructure_type || resolutionForm.network_type;
    if (!infraType || !props.categoriesMap) return [];
    return props.categoriesMap[infraType] || [];
});

const defaultMaterialList = [
    'Konektor RJ-45 Cat6',
    'Patch Cord UTP Cat6',
    'Kabel UTP / LAN Cat6',
    'Kabel Drop Core Fiber Optic (1-Core / 2-Core)',
    'Patch Cord Fiber Optic (SC-SC / LC-SC)',
    'Pigtail Fiber Optic SC/UPC',
    'Fast Connector SC/UPC',
    'Fast Connector SC/APC',
    'Protection Sleeve FO (Splicing)',
    'Optical Termination Box (OTB)',
    'Optical Distribution Point (ODP)',
    'Adaptor Fiber Optic SC/UPC',
    'SFP Transceiver Module (1.25G / 10G)',
    'Media Converter FO to LAN',
    'PoE Injector (24V / 48V)',
    'Power Supply / Adaptor (12V / 24V)',
    'Access Point (AP)',
    'Switch Hub (8-Port / 16-Port / 24-Port)',
    'Router Board / Mikrotik',
    'Stop Kontak / Steker Listrik',
    'Kabel Ties / Velcro',
    'Pipa Conduit / Cable Protector Duct',
    'Isolasi Listrik / Heat Shrink',
];

const materialOptions = computed(() => {
    return (props.availableMaterials && props.availableMaterials.length > 0)
        ? props.availableMaterials.map((m: any) => m.name)
        : [...defaultMaterialList];
});

const handleMaterialChange = (row: MaterialRow) => {
    if (row.material === 'none') {
        row.quantity = null;
        row.unit = 'pcs';
        return;
    }
    if (!row.quantity || row.quantity < 1) {
        row.quantity = 1;
    }
    if (row.material) {
        if (props.availableMaterials && props.availableMaterials.length > 0) {
            const found = props.availableMaterials.find((m: any) => m.name.toLowerCase() === row.material.toLowerCase());
            if (found && found.default_unit) {
                row.unit = found.default_unit;
                return;
            }
        }
        if (row.material.toLowerCase().includes('kabel') || row.material.toLowerCase().includes('drop core')) {
            row.unit = 'meter';
        } else if (row.material.toLowerCase().includes('isolasi')) {
            row.unit = 'roll';
        } else if (row.material.toLowerCase().includes('pipa')) {
            row.unit = 'batang';
        } else if (row.material.toLowerCase().includes('ties')) {
            row.unit = 'pack';
        } else if (
            row.material.toLowerCase().includes('switch') || 
            row.material.toLowerCase().includes('router') || 
            row.material.toLowerCase().includes('converter') || 
            row.material.toLowerCase().includes('module') || 
            row.material.toLowerCase().includes('access point') ||
            row.material.toLowerCase().includes('box') ||
            row.material.toLowerCase().includes('supply') ||
            row.material.toLowerCase().includes('injector') ||
            row.material.toLowerCase().includes('modem') ||
            row.material.toLowerCase().includes('ont')
        ) {
            row.unit = 'unit';
        } else {
            row.unit = 'pcs';
        }
    }
};

const unitOptions = [
    'pcs',
    'meter',
    'unit',
    'roll',
    'set',
    'batang',
    'buah',
    'pack',
    'box',
];

interface MaterialRow {
    material: string;
    quantity: number | null;
    unit: string;
}

const parseExistingMaterials = (str: string | null | undefined): MaterialRow[] => {
    if (!str || !str.trim()) {
        return [{ material: '', quantity: 1, unit: 'pcs' }];
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

        const matchedMat = materialOptions.value.find(m => m.toLowerCase() === rawName.toLowerCase());
        
        let finalUnit = unit;
        if (props.availableMaterials && props.availableMaterials.length > 0) {
            const found = props.availableMaterials.find((m: any) => m.name.toLowerCase() === (matchedMat || rawName).toLowerCase());
            if (found && found.default_unit) {
                finalUnit = found.default_unit;
            }
        }
        if (!finalUnit || !unitOptions.includes(finalUnit)) {
            finalUnit = unitOptions.includes(unit) ? unit : 'pcs';
        }

        if (matchedMat) {
            rows.push({
                material: matchedMat,
                quantity: isNaN(qty) ? 1 : qty,
                unit: finalUnit,
            });
        }
    }

    return rows.length > 0 ? rows : [{ material: '', quantity: 1, unit: 'pcs' }];
};

const materialRows = ref<MaterialRow[]>(parseExistingMaterials(props.ticket.materials_used));

const syncMaterialsUsed = () => {
    const validRows = materialRows.value.filter(r => r.material && r.material !== 'none' && (r.quantity ?? 0) > 0);
    if (validRows.length > 0) {
        resolutionForm.materials_used = validRows.map(r => {
            return `${r.material} (${r.quantity} ${r.unit})`;
        }).join(', ');
    } else {
        resolutionForm.materials_used = '';
    }
};

const addMaterialRow = () => {
    materialRows.value.push({
        material: '',
        quantity: 1,
        unit: 'pcs',
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
        };
    }
    syncMaterialsUsed();
};

const testResultOptions = [
    'Normal',
    'Normal / Berfungsi Baik',
    'Stabil / Link Up',
    'Optimal',
    'Normal dengan Pemantauan',
];

const selectedTestResult = ref<string>(
    props.ticket.test_result && testResultOptions.includes(props.ticket.test_result)
        ? props.ticket.test_result
        : 'Normal'
);

const syncTestResult = () => {
    resolutionForm.test_result = selectedTestResult.value;
};

watch(selectedTestResult, () => {
    syncTestResult();
});

watch(materialRows, () => {
    syncMaterialsUsed();
}, { deep: true });

watch(isResolutionModalOpen, (open) => {
    if (open) {
        resolutionForm.clearErrors();
        resolutionForm.actual_repair_location = props.ticket.actual_repair_location || props.ticket.location_details || '';
        resolutionForm.infrastructure_type = props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic';
        resolutionForm.network_type = props.ticket.infrastructure_type || props.ticket.network_type || 'Fiber optic';
        resolutionForm.category_id = props.ticket.category_id ? props.ticket.category_id.toString() : '';
        resolutionForm.inspection_result = props.ticket.inspection_result || '';
        resolutionForm.root_cause = props.ticket.root_cause || '';
        resolutionForm.action_taken = props.ticket.action_taken || '';
        resolutionForm.test_parameters = props.ticket.test_parameters || '';
        resolutionForm.notes = props.ticket.resolution_note || '';

        // Handle affected device
        if (props.ticket.affected_device && affectedDeviceOptions.value.includes(props.ticket.affected_device)) {
            selectedAffectedDevice.value = props.ticket.affected_device;
        } else {
            selectedAffectedDevice.value = '';
        }
        syncAffectedDevice();

        materialRows.value = parseExistingMaterials(props.ticket.materials_used);
        syncMaterialsUsed();

        if (props.ticket.test_result && testResultOptions.includes(props.ticket.test_result)) {
            selectedTestResult.value = props.ticket.test_result;
        } else {
            selectedTestResult.value = 'Normal';
        }
        syncTestResult();
    }
});

const submitResolution = () => {
    syncAffectedDevice();
    syncMaterialsUsed();
    syncTestResult();
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

        // 2. Subscribe to Internal Channel (Admin & Assigned Technician ONLY)
        if (role.value === 'admin' || (role.value === 'technician' && isAssignedTechnician.value)) {
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

                <!-- Kanan: Action Buttons (Selalu Flex, Overflow Visible agar Animasi Bebas) -->
                <div class="flex items-center gap-2 shrink-0 flex-wrap sm:flex-nowrap overflow-visible py-1">
                    <!-- Tombol Diskusi & Riwayat -->
                    <Button 
                        @click="isDrawerOpen = true" 
                        size="sm" 
                        variant="outline" 
                        class="h-9 px-3.5 border-slate-200 text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs sm:text-sm font-medium relative overflow-visible whitespace-nowrap"
                    >
                        <MessageSquare class="w-4 h-4 mr-1.5 text-kominfo-primary" />
                        <span>Diskusi & Riwayat</span>
                        <span 
                            v-if="unreadRepliesCount > 0" 
                            class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center shadow-xs animate-bounce z-20 pointer-events-none"
                        >
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

                    <!-- OPD Action: Cancel Pending Report -->
                    <Button 
                        v-if="canCancelByReporter" 
                        @click="isCancelModalOpen = true" 
                        size="sm" 
                        variant="destructive" 
                        class="h-9 px-3.5 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <XCircle class="w-4 h-4 mr-1.5" /> Batalkan Laporan
                    </Button>

                    <!-- Technician Action: In Progress & On-Hold -->
                    <Button 
                        v-if="canHold" 
                        @click="isHoldModalOpen = true" 
                        size="sm" 
                        variant="outline" 
                        class="h-9 px-3.5 border-amber-300 text-amber-900 bg-amber-50/50 hover:bg-amber-100 text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <PauseCircle class="w-4 h-4 mr-1.5 text-amber-700" /> Tunda (On-Hold)
                    </Button>

                    <Button 
                        v-if="canResume" 
                        @click="submitResume" 
                        size="sm" 
                        :disabled="resumeForm.processing"
                        class="h-9 px-3.5 bg-amber-600 hover:bg-amber-700 text-white text-xs sm:text-sm font-medium whitespace-nowrap"
                    >
                        <PlayCircle class="w-4 h-4 mr-1.5" /> {{ resumeForm.processing ? 'Melanjutkan...' : 'Lanjutkan Pengerjaan (Resume)' }}
                    </Button>

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
            
            <!-- 0. On-Hold Status Banner -->
            <div v-if="ticket.status === 'on_hold'" class="p-5 bg-amber-50 border border-amber-200 rounded-xl space-y-3">
                <div class="flex items-start gap-3">
                    <PauseCircle class="w-6 h-6 text-amber-600 shrink-0 mt-0.5" />
                    <div class="space-y-1 text-sm text-amber-950 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-bold text-amber-950 text-base">
                                Penanganan Lapangan Ditunda Sementara (On-Hold)
                            </p>
                            <span v-if="ticket.hold_started_at" class="text-xs text-amber-700 font-medium">
                                Sejak: {{ formatDate(ticket.hold_started_at) }}
                            </span>
                        </div>
                        <p class="text-amber-900 text-xs sm:text-sm font-medium">
                            Kategori Hambatan: <span class="font-bold text-amber-950">{{ getHoldCategoryLabel(ticket.hold_reason_category) }}</span>
                        </p>
                        <p v-if="ticket.hold_reason_note" class="text-amber-800 text-xs sm:text-sm leading-relaxed bg-amber-100/60 p-3 rounded-lg border border-amber-200/70 mt-2">
                            <span class="font-semibold text-amber-900">Catatan Kendala:</span> {{ ticket.hold_reason_note }}
                        </p>
                        <div class="pt-2 border-t border-amber-200/80 mt-2">
                            <span class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                                <Clock class="w-4 h-4 text-amber-600" />
                                Timer batas target SLA dijeda otomatis dan akan disesuaikan saat penanganan dilanjutkan.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. Cancelled / Rejection Banner -->
            <div v-if="ticket.status === 'cancelled'" class="p-5 bg-rose-50 border border-rose-200 rounded-xl space-y-3">
                <div class="flex items-start gap-3">
                    <AlertTriangle class="w-6 h-6 text-rose-600 shrink-0 mt-0.5" />
                    <div class="space-y-1 text-sm text-rose-900 flex-1">
                        <p class="font-bold text-rose-950">
                            {{ cancellationInfo?.isCancelledByReporter ? 'Laporan Dibatalkan oleh Pelapor OPD' : 'Laporan Tiket Ditolak oleh Admin Diskominfo' }}
                        </p>
                        <p class="text-rose-800 leading-relaxed font-normal">
                            {{ cancellationInfo?.comment }}
                        </p>
                        <div v-if="!cancellationInfo?.isCancelledByReporter && isWithin72Hours" class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-rose-200 mt-2">
                            <span class="text-xs font-semibold text-rose-900 flex items-center gap-1.5">
                                <Clock class="w-4 h-4 text-rose-600" />
                                Masa perbaikan laporan aktif: Tersisa {{ remainingResubmitHours }} jam
                            </span>
                            <Button v-if="canResubmit" @click="isResubmitModalOpen = true" size="sm" class="bg-rose-600 hover:bg-rose-700 text-white text-xs">
                                Perbaiki Laporan Sekarang
                            </Button>
                        </div>
                        <div v-else-if="!cancellationInfo?.isCancelledByReporter" class="text-xs text-rose-700 pt-1 font-medium italic">
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
                                    {{ (ticket.infrastructure_type || ticket.network_type) ? getNetworkLabel(ticket.infrastructure_type || ticket.network_type) : 'Menunggu diagnosa teknisi' }}
                                </p>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ ticket.category ? ticket.category.name : ((ticket.infrastructure_type || ticket.network_type) ? 'Kategori belum ditentukan' : 'Diidentifikasi di lokasi') }}
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
                                <div class="mt-1">
                                    <LiveTicketTimer :ticket="ticket" variant="inline" />
                                </div>
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
                    <div v-if="ticket.resolution_note || ticket.action_taken || ticket.resolved_at">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 pb-2 border-b border-slate-100 flex items-center gap-2">
                            <CheckCircle2 class="w-4 h-4 text-emerald-500" /> Hasil Penanganan & Berita Acara Perbaikan Lapangan
                        </h3>

                        <!-- Grid Data Penyelesaian -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 p-4 rounded-lg bg-slate-50/60 border border-slate-200/80 mb-5">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Status Pengerjaan</p>
                                <p class="font-bold text-emerald-600 text-sm mt-0.5">Selesai Ditangani</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Waktu Penyelesaian</p>
                                <p class="font-medium text-slate-800 text-sm mt-0.5">{{ ticket.resolved_at ? formatDate(ticket.resolved_at) : '-' }}</p>
                            </div>
                            <div v-if="ticket.affected_device">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Perangkat / Node Terdampak</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ ticket.affected_device }}</p>
                            </div>
                            <div v-if="ticket.actual_repair_location">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Titik Lokasi Real Perbaikan</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{{ ticket.actual_repair_location }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Klasifikasi Riil</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">
                                    {{ ticket.infrastructure_type ? getNetworkLabel(ticket.infrastructure_type) : '-' }}
                                </p>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ ticket.category ? ticket.category.name : '-' }}
                                </p>
                            </div>
                        </div>

                        <!-- Detail Diagnosa & Penyebab -->
                        <div v-if="ticket.inspection_result || ticket.root_cause" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div v-if="ticket.inspection_result" class="bg-white p-4 rounded-lg border border-slate-200 space-y-1">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <Search class="w-3.5 h-3.5 text-blue-500" /> Hasil Pemeriksaan
                                </p>
                                <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">{{ ticket.inspection_result }}</p>
                            </div>

                            <div v-if="ticket.root_cause" class="bg-white p-4 rounded-lg border border-slate-200 space-y-1">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <AlertTriangle class="w-3.5 h-3.5 text-amber-500" /> Penyebab Gangguan
                                </p>
                                <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">{{ ticket.root_cause }}</p>
                            </div>
                        </div>

                        <!-- Tindakan yang Dilakukan -->
                        <div v-if="ticket.action_taken || ticket.resolution_note" class="bg-white p-4 rounded-lg border border-slate-200 mb-4 space-y-1">
                            <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <Wrench class="w-3.5 h-3.5 text-emerald-500" /> Tindakan yang Dilakukan
                            </p>
                            <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">{{ ticket.action_taken || ticket.resolution_note }}</p>
                        </div>

                        <!-- Material & Parameter Pengujian -->
                        <div v-if="ticket.materials_used || ticket.test_result || ticket.test_parameters" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Material / Perangkat -->
                            <div v-if="ticket.materials_used" class="bg-white p-4 rounded-lg border border-slate-200 space-y-2">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <Boxes class="w-3.5 h-3.5 text-indigo-500" /> Material / Perangkat Digunakan
                                </p>
                                <div class="flex flex-wrap gap-1.5 pt-0.5">
                                    <span 
                                        v-for="(item, idx) in ticket.materials_used.split(',').map((s: string) => s.trim()).filter(Boolean)" 
                                        :key="idx"
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-50 text-slate-800 border border-slate-200 shadow-2xs"
                                    >
                                        {{ item }}
                                    </span>
                                </div>
                            </div>

                            <!-- Hasil Pengujian & Parameter -->
                            <div v-if="ticket.test_result || ticket.test_parameters" class="bg-white p-4 rounded-lg border border-slate-200 space-y-2">
                                <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <Activity class="w-3.5 h-3.5 text-emerald-600" /> Hasil Pengujian & Parameter Mutu
                                </p>
                                <div v-if="ticket.test_result">
                                    <p class="text-[11px] font-semibold text-slate-500">Hasil Uji:</p>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 mt-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                                        {{ ticket.test_result }}
                                    </span>
                                </div>
                                <div v-if="ticket.test_parameters">
                                    <p class="text-[11px] font-semibold text-slate-500">Parameter Uji:</p>
                                    <div class="text-xs font-mono bg-slate-50 p-2 rounded text-slate-800 border border-slate-200 mt-1 whitespace-pre-wrap leading-relaxed">
                                        {{ ticket.test_parameters }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Catatan Tambahan (jika ada dan beda dari action_taken) -->
                        <div v-if="ticket.resolution_note && ticket.action_taken && ticket.resolution_note !== ticket.action_taken" class="bg-white p-4 rounded-lg border border-slate-200 mb-4 space-y-1">
                            <p class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                <FileText class="w-3.5 h-3.5 text-slate-500" /> Catatan Tambahan
                            </p>
                            <p class="text-sm text-slate-800 leading-relaxed whitespace-pre-wrap">{{ ticket.resolution_note }}</p>
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
            <DialogContent class="sm:max-w-[550px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Verifikasi & Disposisi Tim Teknisi</DialogTitle>
                    <DialogDescription>
                        Tentukan tingkat prioritas penanganan dan tugaskan Tim Teknisi. Jenis infrastruktur dan kategori masalah teknis akan diidentifikasi langsung oleh teknisi di lokasi.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitVerifyAndAssign" class="space-y-4 pt-1">
                    <!-- Priority Row -->
                    <div>
                        <InputLabel for="verify_priority" value="Tingkat Prioritas Urgensi *" class="text-xs font-semibold text-slate-700" />
                        <Select v-model="verifyForm.priority">
                            <SelectTrigger class="mt-1">
                                <SelectValue placeholder="Pilih Prioritas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="emergency">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                                        <span class="font-semibold text-rose-600">Darurat (Emergency)</span>
                                        <span class="text-xs text-slate-500">- Target SLA 4 Jam</span>
                                    </div>
                                </SelectItem>
                                <SelectItem value="high">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                        <span class="font-semibold text-amber-600">Tinggi (High)</span>
                                        <span class="text-xs text-slate-500">- Target SLA 8 Jam</span>
                                    </div>
                                </SelectItem>
                                <SelectItem value="medium">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                        <span class="font-semibold text-blue-600">Sedang (Medium)</span>
                                        <span class="text-xs text-slate-500">- Target SLA 24 Jam</span>
                                    </div>
                                </SelectItem>
                                <SelectItem value="low">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                        <span class="font-semibold text-slate-600">Rendah (Low)</span>
                                        <span class="text-xs text-slate-500">- Target SLA 48 Jam</span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Target batas waktu penyelesaian (SLA) dihitung otomatis berdasarkan tingkat prioritas penanganan.
                        </p>
                        <InputError :message="verifyForm.errors.priority" class="mt-1" />
                    </div>

                    <!-- Multi-Technician Checkboxes -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <InputLabel value="Pilih Tim Teknisi Penanggung Jawab *" class="text-xs font-semibold text-slate-700" />
                            <span v-if="verifyForm.technician_ids.length > 0" class="text-[11px] font-semibold text-kominfo-primary">
                                {{ verifyForm.technician_ids.length }} teknisi dipilih
                            </span>
                        </div>
                        <div class="border border-slate-200 rounded-lg p-3 max-h-48 overflow-y-auto space-y-1.5 bg-slate-50/50">
                            <label 
                                v-for="tech in technicians" 
                                :key="tech.id"
                                class="flex items-center justify-between text-xs text-slate-800 cursor-pointer hover:bg-white p-2 rounded transition-colors border border-transparent hover:border-slate-200"
                            >
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="checkbox" 
                                        :checked="verifyForm.technician_ids.includes(tech.id)"
                                        @change="toggleVerifyTechnician(tech.id)"
                                        class="rounded border-slate-300 text-kominfo-primary focus:ring-kominfo-primary w-4 h-4"
                                    />
                                    <span :class="verifyForm.technician_ids.includes(tech.id) ? 'font-semibold text-slate-900' : 'text-slate-700'">{{ tech.name }}</span>
                                    <span v-if="verifyForm.technician_ids[0] === tech.id" class="text-[10px] bg-blue-100 text-blue-800 font-bold px-1.5 py-0.5 rounded">
                                        Lead
                                    </span>
                                </div>
                                <span v-if="tech.phone_number" class="text-slate-400 font-mono text-[11px]">({{ tech.phone_number }})</span>
                            </label>
                            <div v-if="!technicians || technicians.length === 0" class="text-xs text-slate-400 italic text-center py-2">
                                Tidak ada personil teknisi aktif.
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Dapat memilih lebih dari 1 personil untuk penugasan tim kolaboratif (teknisi pertama menjadi Lead).</p>
                        <InputError :message="verifyForm.errors.technician_ids" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isVerifyModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="verifyForm.processing || verifyForm.technician_ids.length === 0" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
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

        <!-- Cancel Ticket Modal (OPD Self-Cancellation) -->
        <Dialog v-model:open="isCancelModalOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle class="text-slate-900 flex items-center gap-2">
                        <XCircle class="w-5 h-5 text-rose-600" />
                        Batalkan Laporan Gangguan
                    </DialogTitle>
                    <DialogDescription class="text-slate-600 text-xs sm:text-sm">
                        Apakah Anda yakin ingin membatalkan laporan ini? Tindakan ini akan menghentikan proses verifikasi oleh Admin Diskominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCancelByReporter" class="space-y-4 pt-2">
                    <div>
                        <InputLabel value="Pilih Alasan Pembatalan *" class="text-xs font-medium mb-2 text-slate-700" />
                        <div class="space-y-2">
                            <label 
                                v-for="option in quickCancelReasons" 
                                :key="option"
                                class="flex items-start gap-2.5 p-2.5 rounded-lg border cursor-pointer text-xs transition-colors"
                                :class="selectedQuickReason === option ? 'border-rose-300 bg-rose-50/60 font-semibold text-rose-950' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input 
                                    type="radio" 
                                    name="cancel_quick_reason" 
                                    :value="option" 
                                    :checked="selectedQuickReason === option"
                                    @change="selectQuickCancelReason(option)"
                                    class="mt-0.5 text-rose-600 focus:ring-rose-500 border-slate-300"
                                />
                                <span>{{ option }}</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="cancel_reason_text" value="Penjelasan Alasan Pembatalan *" class="text-xs font-medium text-slate-700" />
                        <Textarea 
                            id="cancel_reason_text"
                            v-model="cancelForm.reason"
                            placeholder="Tuliskan keterangan detail alasan pembatalan laporan (min. 5 karakter)..."
                            rows="3"
                            class="mt-1 text-sm bg-white"
                            required
                        />
                        <div class="flex items-center justify-between mt-1 text-[11px] text-slate-400">
                            <span>Minimal 5 karakter</span>
                            <span>{{ cancelForm.reason.length }}/500</span>
                        </div>
                        <InputError :message="cancelForm.errors.reason" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <Button type="button" variant="outline" @click="isCancelModalOpen = false">Batal</Button>
                        <Button 
                            type="submit" 
                            :disabled="cancelForm.processing || cancelForm.reason.trim().length < 5" 
                            variant="destructive"
                            class="bg-rose-600 hover:bg-rose-700 text-white"
                        >
                            {{ cancelForm.processing ? 'Membatalkan...' : 'Ya, Batalkan Laporan' }}
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
            <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Laporan Penyelesaian & Berita Acara Perbaikan</DialogTitle>
                    <DialogDescription>
                        Lengkapi rincian berita acara hasil pekerjaan teknisi secara terperinci untuk peninjauan mutu oleh Admin Diskominfo.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitResolution" class="space-y-4 pt-1">
                    <!-- Seksi 1: Perangkat & Klasifikasi Teknis Lapangan -->
                    <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                        <div class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                            Perangkat & Klasifikasi Infrastruktur
                        </div>

                        <!-- Perangkat/Node Terdampak & Titik Lokasi Real Perbaikan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Perangkat / Node Terdampak *" class="text-xs font-semibold text-slate-700" />
                                <Select v-model="selectedAffectedDevice">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Perangkat / Node Terdampak" />
                                    </SelectTrigger>
                                    <SelectContent class="max-h-56">
                                        <SelectItem v-for="dev in affectedDeviceOptions" :key="dev" :value="dev">
                                            {{ dev }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="resolutionForm.errors.affected_device" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel for="actual_repair_location" value="Titik Lokasi Real Perbaikan *" class="text-xs font-semibold text-slate-700" />
                                <Input 
                                    id="actual_repair_location"
                                    type="text" 
                                    v-model="resolutionForm.actual_repair_location" 
                                    placeholder="Cth: Ruang Server Lt. 1 / Tiang ODP-PAL-04"
                                    class="bg-white mt-1 text-sm"
                                    required
                                />
                                <InputError :message="resolutionForm.errors.actual_repair_location" class="mt-1" />
                            </div>
                        </div>

                        <!-- Jenis Infrastruktur & Kategori Masalah -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Jenis Infrastruktur Riil *" class="text-xs font-semibold text-slate-700" />
                                <Select 
                                    :modelValue="resolutionForm.infrastructure_type || resolutionForm.network_type"
                                    @update:modelValue="(val: any) => {
                                        resolutionForm.infrastructure_type = val;
                                        resolutionForm.network_type = val;
                                        resolutionForm.category_id = '';
                                    }"
                                >
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Infrastruktur" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Fiber optic">Fiber optic</SelectItem>
                                        <SelectItem value="Perangkat/Akses">Perangkat/Akses</SelectItem>
                                        <SelectItem value="Power/poe">Power/poe</SelectItem>
                                        <SelectItem value="Converter">Converter</SelectItem>
                                        <SelectItem value="Layanan/jaringan">Layanan/jaringan</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="resolutionForm.errors.infrastructure_type" class="mt-1" />
                            </div>

                            <div>
                                <InputLabel value="Kategori Masalah Riil *" class="text-xs font-semibold text-slate-700" />
                                <Select v-model="resolutionForm.category_id">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Kategori Riil" />
                                    </SelectTrigger>
                                    <SelectContent class="max-h-56">
                                        <SelectItem v-for="cat in resolutionCategories" :key="cat.id" :value="cat.id.toString()">
                                            {{ cat.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="resolutionForm.errors.category_id" class="mt-1" />
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-500">
                            Penetapan kategori riil akan menyesuaikan target SLA berdasarkan standar durasi perbaikan fisik.
                        </p>
                    </div>

                    <!-- Seksi 2: Diagnosa & Tindakan Teknis -->
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Hasil Pemeriksaan -->
                            <div>
                                <InputLabel for="inspection_result" value="Hasil Pemeriksaan Awal *" class="text-xs font-semibold text-slate-700" />
                                <Textarea 
                                    id="inspection_result"
                                    v-model="resolutionForm.inspection_result"
                                    placeholder="Cth: Lampu indikator LOS merah berkedip, port switch tidak aktif..."
                                    rows="2"
                                    class="mt-1 text-sm bg-white"
                                    required
                                />
                                <InputError :message="resolutionForm.errors.inspection_result" class="mt-1" />
                            </div>

                            <!-- Penyebab -->
                            <div>
                                <InputLabel for="root_cause" value="Penyebab Gangguan / Kerusakan *" class="text-xs font-semibold text-slate-700" />
                                <Textarea 
                                    id="root_cause"
                                    v-model="resolutionForm.root_cause"
                                    placeholder="Cth: Konektor RJ-45 korosi dan kabel patch cord tertekuk..."
                                    rows="2"
                                    class="mt-1 text-sm bg-white"
                                    required
                                />
                                <InputError :message="resolutionForm.errors.root_cause" class="mt-1" />
                            </div>
                        </div>

                        <!-- Tindakan yang Dilakukan -->
                        <div>
                            <InputLabel for="action_taken" value="Tindakan yang Dilakukan *" class="text-xs font-semibold text-slate-700" />
                            <Textarea 
                                id="action_taken"
                                v-model="resolutionForm.action_taken"
                                placeholder="Cth: Mengganti kabel patch cord baru sepanjang 3 meter, melakukan crimping ulang RJ-45, dan konfigurasi port..."
                                rows="3"
                                class="mt-1 text-sm bg-white"
                                required
                            />
                            <InputError :message="resolutionForm.errors.action_taken" class="mt-1" />
                        </div>

                        <!-- Material / Perangkat yang Digunakan -->
                        <div class="space-y-2 pt-2 border-t border-slate-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <InputLabel value="Material / Perangkat yang Digunakan" class="text-xs font-semibold text-slate-700" />
                                    <p class="text-[11px] text-slate-500">Pilih material dan tentukan jumlah. Satuan otomatis terisi sesuai master data.</p>
                                </div>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm" 
                                    class="h-7 px-2.5 text-xs font-medium cursor-pointer"
                                    @click="addMaterialRow"
                                >
                                    + Tambah Material
                                </Button>
                            </div>

                            <div class="space-y-2">
                                <div 
                                    v-for="(row, index) in materialRows" 
                                    :key="index" 
                                    class="p-2.5 rounded-lg border border-slate-200 bg-slate-50/80 space-y-2"
                                >
                                    <div class="grid grid-cols-12 gap-2 items-center">
                                        <!-- Dropdown Material / Perangkat -->
                                        <div :class="row.material === 'none' ? 'col-span-10 sm:col-span-6' : 'col-span-12 sm:col-span-6'">
                                            <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                                Material / Perangkat
                                            </label>
                                            <Select v-model="row.material" @update:model-value="() => handleMaterialChange(row)">
                                                <SelectTrigger class="bg-white h-9 text-xs">
                                                    <SelectValue placeholder="-- Pilih Material / Perangkat --" />
                                                </SelectTrigger>
                                                <SelectContent class="max-h-56">
                                                    <SelectItem value="none">-- Tanpa Penggantian Material --</SelectItem>
                                                    <SelectItem v-for="mat in materialOptions" :key="mat" :value="mat">
                                                        {{ mat }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <!-- Jika Tanpa Penggantian Material -->
                                        <div v-if="row.material === 'none'" class="col-span-10 sm:col-span-5 flex items-center text-xs text-slate-500 py-1.5">
                                            <span class="inline-flex items-center px-2.5 py-1 bg-slate-100 rounded text-[11px] text-slate-600 border border-slate-200">
                                                Tidak memerlukan penggantian material fisik
                                            </span>
                                        </div>

                                        <!-- Jika Ada Material -->
                                        <template v-else>
                                            <!-- Input Number Jumlah -->
                                            <div class="col-span-6 sm:col-span-3">
                                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                                    Jumlah
                                                </label>
                                                <Input 
                                                    type="number" 
                                                    min="1" 
                                                    step="1"
                                                    v-model.number="row.quantity" 
                                                    placeholder="1"
                                                    class="bg-white h-9 text-xs"
                                                />
                                            </div>

                                            <!-- Display Satuan (Non-Editable) -->
                                            <div class="col-span-4 sm:col-span-2">
                                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                                    Satuan
                                                </label>
                                                <div 
                                                    class="h-9 px-3 flex items-center justify-center bg-slate-100 border border-slate-200 rounded-md text-xs font-semibold text-slate-700 select-none cursor-not-allowed text-center"
                                                    title="Satuan otomatis terisi sesuai master data material"
                                                >
                                                    {{ row.unit || 'pcs' }}
                                                </div>
                                            </div>
                                        </template>

                                        <!-- Tombol Hapus Row -->
                                        <div class="col-span-2 sm:col-span-1 flex items-end justify-center pt-4">
                                            <Button 
                                                type="button" 
                                                variant="ghost" 
                                                size="icon" 
                                                class="h-8 w-8 text-slate-400 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                                                title="Hapus baris material"
                                                @click="removeMaterialRow(index)"
                                            >
                                                <Trash2 class="w-3.5 h-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="resolutionForm.errors.materials_used" class="mt-1" />
                        </div>
                    </div>

                    <!-- Seksi 3: Hasil Pengujian & Parameter Mutu -->
                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Hasil Pengujian -->
                            <div>
                                <InputLabel value="Hasil Pengujian Akhir *" class="text-xs font-semibold text-slate-700" />
                                <Select v-model="selectedTestResult">
                                    <SelectTrigger class="bg-white mt-1">
                                        <SelectValue placeholder="Pilih Hasil Pengujian" />
                                    </SelectTrigger>
                                    <SelectContent class="max-h-56">
                                        <SelectItem v-for="opt in testResultOptions" :key="opt" :value="opt">
                                            {{ opt }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="resolutionForm.errors.test_result" class="mt-1" />
                            </div>

                            <!-- Parameter Uji -->
                            <div>
                                <InputLabel for="test_parameters" value="Parameter Uji (Opsional)" class="text-xs font-semibold text-slate-700" />
                                <Textarea 
                                    id="test_parameters"
                                    v-model="resolutionForm.test_parameters"
                                    placeholder="Cth: Ping 8.8.8.8 (1ms, loss 0%), Redaman -19 dBm"
                                    rows="2"
                                    class="mt-1 text-sm bg-white"
                                />
                                <p class="text-[11px] text-slate-400 mt-1">Hasil ping jaringan atau nilai ukur alat lainnya.</p>
                                <InputError :message="resolutionForm.errors.test_parameters" class="mt-1" />
                            </div>
                        </div>

                        <!-- Catatan Tambahan -->
                        <div>
                            <InputLabel for="notes" value="Catatan / Rekomendasi Tambahan" class="text-xs font-semibold text-slate-700" />
                            <Textarea 
                                id="notes"
                                v-model="resolutionForm.notes"
                                placeholder="Cth: Disarankan merapikan kabel stopkontak di ruang rapat agar tidak mudah tersenggol staf..."
                                rows="2"
                                class="mt-1 text-sm bg-white"
                            />
                            <InputError :message="resolutionForm.errors.notes" class="mt-1" />
                        </div>
                    </div>

                    <!-- Seksi 4: Foto Bukti Hasil Perbaikan -->
                    <div class="pt-2 border-t border-slate-100">
                        <InputLabel value="Foto Bukti Hasil Perbaikan" class="text-xs font-semibold text-slate-700 mb-1" />
                        <p class="text-[11px] text-slate-500 mb-2">Unggah foto bukti fisik hasil perbaikan (maks. 3 file foto, JPG/PNG, maks. 5MB per file).</p>
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
                        <Button type="submit" :disabled="resolutionForm.processing || !resolutionForm.action_taken || !resolutionForm.affected_device || !resolutionForm.actual_repair_location" class="bg-emerald-600 hover:bg-emerald-700 text-white">
                            {{ resolutionForm.processing ? 'Mengirim...' : 'Kirim Laporan Perbaikan' }}
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

        <!-- 7. Hold Ticket Modal (Technician / Admin) -->
        <Dialog v-model:open="isHoldModalOpen">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Tunda Penanganan Tiket (On-Hold)</DialogTitle>
                    <DialogDescription>
                        Gunakan status ini bila pengerjaan di lapangan mengalami hambatan eksternal atau perlu eskalasi. Timer target SLA akan dijeda otomatis selama masa tunda.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitHold" class="space-y-4 pt-1">
                    <div>
                        <InputLabel for="hold_category" value="Kategori Hambatan / Alasan Penundaan *" class="text-xs font-semibold text-slate-700" />
                        <Select v-model="holdForm.hold_reason_category">
                            <SelectTrigger id="hold_category" class="mt-1">
                                <SelectValue placeholder="Pilih Kategori Kendala" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem 
                                    v-for="cat in holdReasonCategories" 
                                    :key="cat.value" 
                                    :value="cat.value"
                                >
                                    {{ cat.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="holdForm.errors.hold_reason_category" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="hold_reason_note" value="Rincian Kendala & Rencana Tindak Lanjut *" class="text-xs font-semibold text-slate-700" />
                        <Textarea 
                            id="hold_reason_note"
                            v-model="holdForm.hold_reason_note"
                            placeholder="Cth: Telah dilaporkan ke ISP Telkom (No. Tiket: INC12345). Menunggu teknisi kabel tiba..."
                            rows="4"
                            class="mt-1 text-sm bg-white"
                            required
                        />
                        <p class="text-[11px] text-slate-500 mt-1">
                            Tuliskan nomor tiket ISP, nama teknisi vendor, nomor kontak, atau estimasi material yang ditunggu.
                        </p>
                        <InputError :message="holdForm.errors.hold_reason_note" class="mt-1" />
                    </div>

                    <DialogFooter class="pt-3 border-t border-slate-100">
                        <Button type="button" variant="outline" @click="isHoldModalOpen = false">Batal</Button>
                        <Button type="submit" :disabled="holdForm.processing || !holdForm.hold_reason_note" class="bg-amber-600 hover:bg-amber-700 text-white">
                            {{ holdForm.processing ? 'Memproses...' : 'Konfirmasi Tunda (On-Hold)' }}
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

                                        <div v-if="role === 'admin' || (role === 'technician' && isAssignedTechnician)" class="flex items-center space-x-1.5">
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
