<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link, router, useForm } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/Components/FileUpload.vue';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Table,
  TableHeader,
  TableBody,
  TableRow,
  TableHead,
  TableCell,
  TableFooter,
} from '@/components/ui/table';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Ticket,
  CheckCircle2,
  Clock,
  Activity,
  ShieldCheck,
  AlertTriangle,
  ArrowRight,
  BarChart3,
  Calendar,
  SlidersHorizontal,
  PieChart,
  Network,
  Gauge,
  TrendingUp,
  Eye,
  Star,
  MapPin,
  Plus,
  MessageSquare,
  ExternalLink,
} from 'lucide-vue-next';

// Chart.js & vue-chartjs Integration
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  PointElement,
  LineElement,
  Filler,
} from 'chart.js';
import { Doughnut, Bar, Line } from 'vue-chartjs';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  ArcElement,
  PointElement,
  LineElement,
  Filler
);

interface MonthlyReport {
    period: string;
    month_name: string;
    total_tickets: number;
    in_progress: number;
    closed: number;
    cancelled: number;
    avg_resolution_time: string;
    completion_rate: number;
}

interface ChartDataset {
    labels: string[];
    data: number[];
    colors: string[];
}

interface TicketTrendDataset {
    labels: string[];
    created: number[];
    closed: number[];
}

interface RecentTicket {
    id: number;
    ticket_number: string;
    title: string;
    department_name: string;
    department_code: string;
    reporter_name: string;
    category_name: string;
    technicians_label?: string;
    infrastructure_type?: string;
    network_type?: string;
    priority: string;
    status: string;
    created_at: string;
    created_at_diff: string;
}

interface RecentActivity {
    id: number;
    ticket_id: number;
    ticket_number: string;
    ticket_title: string;
    department_name: string;
    user_name: string;
    user_role: string;
    previous_status: string | null;
    new_status: string;
    comment: string | null;
    created_at: string;
    created_at_diff: string;
}

interface ActiveTask {
    id: number;
    ticket_number: string;
    title: string;
    department_name: string;
    department_code: string;
    location_details: string;
    category_name: string;
    infrastructure_type?: string;
    network_type?: string;
    priority: string;
    due_at: string | null;
    due_human: string;
    is_overdue: boolean;
}

interface RecentFeedback {
    id: number;
    ticket_number: string;
    department_name: string;
    reporter_name: string;
    rating: number;
    feedback_comment: string | null;
    rated_at: string;
    rated_at_diff: string;
}

interface TechnicianResolutionChart {
    labels: string[];
    data: number[];
    counts: number[];
    year: string;
}

const props = withDefaults(defineProps<{
    stats: Record<string, any>;
    monthlyReports?: MonthlyReport[];
    monthlySummary?: MonthlyReport | null;
    availableYears?: string[];
    filterType?: string;
    selectedYear?: string;
    selectedMonth?: string;
    selectedPreset?: string | null;
    startDate?: string | null;
    endDate?: string | null;
    statusDistribution?: ChartDataset | null;
    infrastructureDistribution?: ChartDataset | null;
    networkTypeDistribution?: ChartDataset | null;
    priorityDistribution?: ChartDataset | null;
    ticketTrend?: TicketTrendDataset | null;
    recentTickets?: RecentTicket[];
    recentActivities?: RecentActivity[];
    activeTasks?: ActiveTask[];
    recentFeedbacks?: RecentFeedback[];
    technicianResolutionChart?: TechnicianResolutionChart | null;
}>(), {
    monthlyReports: () => [],
    monthlySummary: null,
    availableYears: () => [],
    filterType: 'year_month',
    selectedYear: '2025',
    selectedMonth: 'all',
    selectedPreset: null,
    startDate: null,
    endDate: null,
    statusDistribution: null,
    infrastructureDistribution: null,
    networkTypeDistribution: null,
    priorityDistribution: null,
    ticketTrend: null,
    recentTickets: () => [],
    recentActivities: () => [],
    activeTasks: () => [],
    recentFeedbacks: () => [],
    technicianResolutionChart: null,
});

const user = computed(() => usePage().props.auth.user as any);
const role = computed(() => user.value?.role);

// OPD Create Ticket Modal & Form State
const isCreateModalOpen = ref(false);
const opdCreateForm = useForm({
    title: '',
    location_details: '',
    description: '',
    attachments: [] as File[],
});

// Common non-technical issue options for OPD
const opdIssueOptions = [
    { value: 'Internet Mati Total (Tidak Ada Koneksi)', label: 'Internet Mati Total (Tidak Ada Koneksi)' },
    { value: 'Koneksi Internet Sangat Lambat / Putus-Nyambung', label: 'Koneksi Internet Sangat Lambat / Putus-Nyambung' },
    { value: 'WiFi Kantor Tidak Terdeteksi / Tidak Bisa Terhubung', label: 'WiFi Kantor Tidak Terdeteksi / Tidak Bisa Terhubung' },
    { value: 'Komputer Tertentu Tidak Bisa Akses Internet', label: 'Komputer Tertentu Tidak Bisa Akses Internet' },
    { value: 'Aplikasi / Website Pemerintah Daerah Tidak Bisa Dibuka', label: 'Aplikasi / Website Pemerintah Daerah Tidak Bisa Dibuka' },
    { value: 'Perangkat / Kabel Jaringan Rusak Fisik atau Terlepas', label: 'Perangkat / Kabel Jaringan Rusak Fisik atau Terlepas' },
    { value: 'other', label: 'Kendala Lainnya (Tuliskan Sendiri)' },
];

const selectedOpdIssueOption = ref<string>('');

const onSelectOpdIssueOption = (val: any) => {
    selectedOpdIssueOption.value = val;
    if (val === 'other') {
        opdCreateForm.title = '';
    } else {
        opdCreateForm.title = val;
    }
};

const openCreateTicketModal = () => {
    opdCreateForm.reset();
    opdCreateForm.clearErrors();
    selectedOpdIssueOption.value = '';
    isCreateModalOpen.value = true;
};

const submitCreateTicket = () => {
    opdCreateForm.post(route('tickets.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            opdCreateForm.reset();
        }
    });
};

// Unified Period Filter Controller
const isFilterOpen = ref(false);
const filterDropdownRef = ref<HTMLElement | null>(null);
const activeTab = ref<'year_month' | 'range'>(props.filterType === 'range' ? 'range' : 'year_month');

// Form state
const formYear = ref(props.selectedYear || '2025');
const formMonth = ref(props.selectedMonth || 'all');
const formPreset = ref(props.selectedPreset || '30d');
const formStartDate = ref(props.startDate || '');
const formEndDate = ref(props.endDate || '');

const selectPreset = (preset: '7d' | '30d' | 'this_month') => {
    formPreset.value = preset;
    const today = new Date();
    const formatDate = (d: Date) => {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    if (preset === '7d') {
        const start = new Date();
        start.setDate(today.getDate() - 6);
        formStartDate.value = formatDate(start);
        formEndDate.value = formatDate(today);
    } else if (preset === '30d') {
        const start = new Date();
        start.setDate(today.getDate() - 29);
        formStartDate.value = formatDate(start);
        formEndDate.value = formatDate(today);
    } else if (preset === 'this_month') {
        const start = new Date(today.getFullYear(), today.getMonth(), 1);
        const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        formStartDate.value = formatDate(start);
        formEndDate.value = formatDate(end);
    }
};

const onCustomDateInput = () => {
    formPreset.value = 'custom';
};

watch(() => [props.filterType, props.selectedYear, props.selectedMonth, props.selectedPreset, props.startDate, props.endDate], () => {
    activeTab.value = props.filterType === 'range' ? 'range' : 'year_month';
    formYear.value = props.selectedYear || '2025';
    formMonth.value = props.selectedMonth || 'all';
    formPreset.value = props.selectedPreset || '30d';
    formStartDate.value = props.startDate || '';
    formEndDate.value = props.endDate || '';
});

const activeFilterLabel = computed(() => {
    if (props.filterType === 'range') {
        if (props.selectedPreset === '7d') return '7 Hari Terakhir';
        if (props.selectedPreset === '30d') return '30 Hari Terakhir';
        if (props.selectedPreset === 'this_month') return 'Bulan Ini';
        if (props.startDate && props.endDate) {
            const formatShort = (dateStr: string) => {
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    const shortMonths: Record<string, string> = {
                        '01': 'Jan', '02': 'Feb', '03': 'Mar', '04': 'Apr',
                        '05': 'Mei', '06': 'Jun', '07': 'Jul', '08': 'Agu',
                        '09': 'Sep', '10': 'Okt', '11': 'Nov', '12': 'Des'
                    };
                    const d = parseInt(parts[2], 10);
                    const m = shortMonths[parts[1]] || parts[1];
                    return `${d} ${m} ${parts[0]}`;
                }
                return dateStr;
            };
            return `${formatShort(props.startDate)} - ${formatShort(props.endDate)}`;
        }
        return 'Rentang Tanggal';
    }
    
    // year_month
    if (props.selectedYear === 'all') return 'Semua Tahun';
    
    if (props.selectedMonth && props.selectedMonth !== 'all') {
        const monthNames: Record<string, string> = {
            '01': 'Januari', '02': 'Februari', '03': 'Maret', '04': 'April',
            '05': 'Mei', '06': 'Juni', '07': 'Juli', '08': 'Agustus',
            '09': 'September', '10': 'Oktober', '11': 'November', '12': 'Desember'
        };
        const m = monthNames[props.selectedMonth] || props.selectedMonth;
        return `${m} ${props.selectedYear}`;
    }
    
    return `Tahun ${props.selectedYear}`;
});

const applyYearMonthFilter = () => {
    isFilterOpen.value = false;
    router.get(route('dashboard'), {
        filter_type: 'year_month',
        year: formYear.value,
        month: formMonth.value,
    }, { preserveState: true, preserveScroll: true });
};

const applyRangeFilter = () => {
    isFilterOpen.value = false;
    if (formPreset.value && formPreset.value !== 'custom') {
        router.get(route('dashboard'), {
            filter_type: 'range',
            preset: formPreset.value,
        }, { preserveState: true, preserveScroll: true });
    } else {
        if (!formStartDate.value || !formEndDate.value) return;
        router.get(route('dashboard'), {
            filter_type: 'range',
            preset: 'custom',
            start_date: formStartDate.value,
            end_date: formEndDate.value,
        }, { preserveState: true, preserveScroll: true });
    }
};

const resetFilter = () => {
    isFilterOpen.value = false;
    formYear.value = props.availableYears?.[0] || '2025';
    formMonth.value = 'all';
    router.get(route('dashboard'), {
        filter_type: 'year_month',
        year: formYear.value,
        month: 'all',
    }, { preserveState: true, preserveScroll: true });
};

const handleClickOutside = (e: MouseEvent) => {
    const target = e.target as HTMLElement;
    if (!target) return;

    if (filterDropdownRef.value && !filterDropdownRef.value.contains(target)) {
        isFilterOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Dynamic progress bar styling
const getProgressBg = (rate: number) => {
    if (rate >= 80) return 'bg-emerald-500';
    if (rate >= 50) return 'bg-amber-500';
    return 'bg-rose-500';
};

const getRateTextColor = (rate: number) => {
    if (rate >= 80) return 'text-emerald-700 font-semibold';
    if (rate >= 50) return 'text-amber-700 font-semibold';
    return 'text-rose-700 font-semibold';
};

// Recent tickets text-only styling (no badge background)
const getNetworkLabel = (type: string) => {
    switch (type) {
        case 'Fiber optic':
        case 'fiber_optic': return 'Fiber optic';
        case 'Perangkat/Akses':
        case 'lan': return 'Perangkat/Akses';
        case 'Power/poe': return 'Power/poe';
        case 'Converter': return 'Converter';
        case 'Layanan/jaringan':
        case 'wifi': return 'Layanan/jaringan';
        default: return type || '-';
    }
};

const getNetworkColor = (type: string) => {
    switch (type) {
        case 'Fiber optic':
        case 'fiber_optic': return 'text-sky-600 font-semibold';
        case 'Perangkat/Akses':
        case 'lan': return 'text-indigo-600 font-semibold';
        case 'Power/poe': return 'text-amber-600 font-semibold';
        case 'Converter': return 'text-pink-600 font-semibold';
        case 'Layanan/jaringan':
        case 'wifi': return 'text-emerald-600 font-semibold';
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

const getRoleLabel = (role: string) => {
    switch (role) {
        case 'admin': return 'Admin';
        case 'technician': return 'Teknisi';
        case 'opd_user': return 'OPD';
        default: return role || 'Sistem';
    }
};

const getRoleColor = (role: string) => {
    switch (role) {
        case 'admin': return 'text-purple-600 font-semibold';
        case 'technician': return 'text-amber-600 font-semibold';
        case 'opd_user': return 'text-blue-600 font-semibold';
        default: return 'text-slate-500 font-semibold';
    }
};

// Chart 1: Status Distribution (Doughnut)
const doughnutChartData = computed(() => {
    if (!props.statusDistribution) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.statusDistribution.labels,
        datasets: [
            {
                data: props.statusDistribution.data,
                backgroundColor: props.statusDistribution.colors,
                borderColor: '#ffffff',
                borderWidth: 2,
                hoverOffset: 4,
            },
        ],
    };
});

const doughnutChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    const label = context.label || '';
                    const value = context.raw || 0;
                    const dataset = context.chart.data.datasets[0].data;
                    const total = dataset.reduce((a: number, b: number) => a + b, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                    return ` ${label}: ${value} tiket (${percentage}%)`;
                },
            },
        },
    },
};

// Chart 2: Priority Distribution (Bar)
const priorityBarChartData = computed(() => {
    if (!props.priorityDistribution) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.priorityDistribution.labels,
        datasets: [
            {
                label: 'Jumlah Tiket Berdasarkan Prioritas',
                data: props.priorityDistribution.data,
                backgroundColor: props.priorityDistribution.colors,
                borderRadius: 6,
                maxBarThickness: 36,
            },
        ],
    };
});

const priorityBarChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    const value = context.raw || 0;
                    const dataset = context.chart.data.datasets[0].data;
                    const total = dataset.reduce((a: number, b: number) => a + b, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                    return ` ${value} tiket (${percentage}%)`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                font: {
                    size: 11,
                },
            },
            grid: {
                color: '#f1f5f9',
            },
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    size: 11,
                    weight: 500,
                },
            },
        },
    },
};

const infraDist = computed(() => props.infrastructureDistribution || props.networkTypeDistribution);

// Chart 3: Infrastructure Distribution (Bar)
const barChartData = computed(() => {
    if (!infraDist.value) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: infraDist.value.labels,
        datasets: [
            {
                label: 'Jumlah Laporan Gangguan',
                data: infraDist.value.data,
                backgroundColor: infraDist.value.colors,
                borderRadius: 6,
                maxBarThickness: 36,
            },
        ],
    };
});

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    const value = context.raw || 0;
                    const dataset = context.chart.data.datasets[0].data;
                    const total = dataset.reduce((a: number, b: number) => a + b, 0);
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0';
                    return ` ${value} tiket (${percentage}%)`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                font: {
                    size: 11,
                },
            },
            grid: {
                color: '#f1f5f9',
            },
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    size: 11,
                    weight: 500,
                },
            },
        },
    },
};

// Chart 4: Ticket Trend (Line Chart)
const lineChartData = computed(() => {
    if (!props.ticketTrend) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.ticketTrend.labels,
        datasets: [
            {
                label: 'Tiket Terselesaikan',
                data: props.ticketTrend.closed,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 6,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
            },
        ],
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label: function (context: any) {
                    return ` ${context.dataset.label}: ${context.raw} tiket`;
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                font: {
                    size: 11,
                },
            },
            grid: {
                color: '#f1f5f9',
            },
        },
        x: {
            grid: {
                display: false,
            },
            ticks: {
                font: {
                    size: 11,
                    weight: 500,
                },
            },
        },
    },
};

// Technician Resolved Ticket Volume Chart (Bar Chart)
const techVolumeChartData = computed(() => {
    if (!props.technicianResolutionChart) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.technicianResolutionChart.labels,
        datasets: [
            {
                label: 'Tiket Selesai',
                data: props.technicianResolutionChart.counts,
                backgroundColor: '#10b981',
                hoverBackgroundColor: '#059669',
                borderRadius: 4,
                maxBarThickness: 28,
            },
        ],
    };
});

const techVolumeChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 12, weight: 'bold' as const },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 6,
            callbacks: {
                label: (context: any) => {
                    const val = context.parsed.y;
                    return ` ${val} tiket tuntas`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                color: '#64748b',
                font: { size: 11 },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: '#f1f5f9',
            },
            ticks: {
                precision: 0,
                color: '#64748b',
                font: { size: 11 },
                callback: (val: any) => `${val} tiket`,
            },
        },
    },
}));

// Technician Resolution Time Chart (Line Chart)
const techResolutionChartData = computed(() => {
    if (!props.technicianResolutionChart) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.technicianResolutionChart.labels,
        datasets: [
            {
                label: 'Rata-rata Durasi (Jam)',
                data: props.technicianResolutionChart.data,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            },
        ],
    };
});

const techResolutionChartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            backgroundColor: '#1e293b',
            titleFont: { size: 12, weight: 'bold' as const },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 6,
            callbacks: {
                label: (context: any) => {
                    const val = context.parsed.y;
                    const idx = context.dataIndex;
                    const count = props.technicianResolutionChart?.counts?.[idx] ?? 0;
                    return ` Rata-rata: ${val} Jam (${count} tiket selesai)`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                color: '#64748b',
                font: { size: 11 },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: '#f1f5f9',
            },
            ticks: {
                color: '#64748b',
                font: { size: 11 },
                callback: (val: any) => `${val} jam`,
            },
        },
    },
}));
</script>

<template>
    <Head title="Dashboard Monitoring" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header Title & Actions -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Dashboard Monitoring</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Ringkasan metrik operasional layanan helpdesk jaringan dan efisiensi kinerja penanganan.
                    </p>
                </div>
                <div class="flex items-center gap-2.5 shrink-0 flex-nowrap">
                    <!-- Unified Period Filter Selector (for Admin) -->
                    <div v-if="role === 'admin'" class="relative shrink-0" ref="filterDropdownRef">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            class="h-9 text-xs bg-white hover:bg-slate-50 border-slate-200 shadow-none font-medium flex items-center gap-1.5 whitespace-nowrap shrink-0"
                            @click="isFilterOpen = !isFilterOpen"
                        >
                            <Calendar class="w-3.5 h-3.5 text-blue-600 shrink-0" />
                            <span class="whitespace-nowrap">{{ activeFilterLabel }}</span>
                            <SlidersHorizontal class="w-3 h-3 ml-1 text-slate-400 shrink-0" />
                        </Button>

                        <!-- Dropdown Panel -->
                        <div 
                            v-if="isFilterOpen" 
                            class="absolute right-0 mt-2 w-[340px] sm:w-[380px] bg-white rounded-xl shadow-xl border border-slate-200 z-50 p-4 animate-in fade-in zoom-in-95 duration-100"
                        >
                            <!-- Tab Header -->
                            <div class="flex p-1 bg-slate-100 rounded-lg text-xs font-semibold mb-4">
                                <button 
                                    type="button" 
                                    class="flex-1 py-1.5 rounded-md transition-all text-center"
                                    :class="activeTab === 'year_month' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="activeTab = 'year_month'"
                                >
                                    Bulan & Tahun
                                </button>
                                <button 
                                    type="button" 
                                    class="flex-1 py-1.5 rounded-md transition-all text-center"
                                    :class="activeTab === 'range' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                    @click="activeTab = 'range'"
                                >
                                    Rentang Tanggal
                                </button>
                            </div>

                            <!-- TAB 1: BULAN & TAHUN -->
                            <div v-if="activeTab === 'year_month'" class="space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[11px] font-medium text-slate-500 block mb-1">Tahun</label>
                                        <select 
                                            v-model="formYear"
                                            class="w-full h-8 text-xs bg-white border border-slate-200 rounded-md px-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-medium cursor-pointer"
                                        >
                                            <option v-for="yr in availableYears" :key="yr" :value="yr">
                                                {{ yr }}
                                            </option>
                                            <option value="all">Semua Tahun</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="text-[11px] font-medium text-slate-500 block mb-1">Bulan</label>
                                        <select 
                                            v-model="formMonth" 
                                            :disabled="formYear === 'all'"
                                            class="w-full h-8 text-xs bg-white border border-slate-200 rounded-md px-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-medium cursor-pointer disabled:bg-slate-50 disabled:text-slate-400"
                                        >
                                            <option value="all">Semua Bulan</option>
                                            <option value="01">Januari</option>
                                            <option value="02">Februari</option>
                                            <option value="03">Maret</option>
                                            <option value="04">April</option>
                                            <option value="05">Mei</option>
                                            <option value="06">Juni</option>
                                            <option value="07">Juli</option>
                                            <option value="08">Agustus</option>
                                            <option value="09">September</option>
                                            <option value="10">Oktober</option>
                                            <option value="11">November</option>
                                            <option value="12">Desember</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                    <button 
                                        type="button" 
                                        class="text-xs text-slate-500 hover:text-slate-800"
                                        @click="resetFilter"
                                    >
                                        Reset
                                    </button>
                                    <Button size="sm" class="h-8 text-xs bg-blue-600 hover:bg-blue-700 text-white" @click="applyYearMonthFilter">
                                        Terapkan Filter
                                    </Button>
                                </div>
                            </div>

                            <!-- TAB 2: RENTANG TANGGAL -->
                            <div v-if="activeTab === 'range'" class="space-y-3">
                                <div>
                                    <label class="text-[11px] font-medium text-slate-500 block mb-1.5">Preset Cepat</label>
                                    <div class="grid grid-cols-3 gap-1.5">
                                        <button 
                                            type="button" 
                                            class="px-2 py-1.5 rounded-lg border text-xs text-center transition-colors font-medium"
                                            :class="formPreset === '7d' ? 'bg-blue-50 border-blue-200 text-blue-700 font-semibold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                                            @click="selectPreset('7d')"
                                        >
                                            7 Hari
                                        </button>
                                        <button 
                                            type="button" 
                                            class="px-2 py-1.5 rounded-lg border text-xs text-center transition-colors font-medium"
                                            :class="formPreset === '30d' ? 'bg-blue-50 border-blue-200 text-blue-700 font-semibold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                                            @click="selectPreset('30d')"
                                        >
                                            30 Hari
                                        </button>
                                        <button 
                                            type="button" 
                                            class="px-2 py-1.5 rounded-lg border text-xs text-center transition-colors font-medium"
                                            :class="formPreset === 'this_month' ? 'bg-blue-50 border-blue-200 text-blue-700 font-semibold' : 'bg-slate-50 hover:bg-slate-100 border-slate-200 text-slate-700'"
                                            @click="selectPreset('this_month')"
                                        >
                                            Bulan Ini
                                        </button>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100">
                                    <label class="text-[11px] font-medium text-slate-500 block mb-1.5">Rentang Kustom</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <span class="text-[10px] text-slate-400 block mb-0.5">Dari</span>
                                            <input 
                                                type="date" 
                                                v-model="formStartDate" 
                                                @input="onCustomDateInput"
                                                class="w-full h-8 px-2 text-xs border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div>
                                            <span class="text-[10px] text-slate-400 block mb-0.5">Sampai</span>
                                            <input 
                                                type="date" 
                                                v-model="formEndDate" 
                                                @input="onCustomDateInput"
                                                class="w-full h-8 px-2 text-xs border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                                    <button 
                                        type="button" 
                                        class="text-xs text-slate-500 hover:text-slate-800"
                                        @click="resetFilter"
                                    >
                                        Reset
                                    </button>
                                    <Button 
                                        size="sm" 
                                        class="h-8 text-xs bg-blue-600 hover:bg-blue-700 text-white" 
                                        :disabled="!formStartDate || !formEndDate"
                                        @click="applyRangeFilter"
                                    >
                                        Terapkan Rentang
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lihat Semua Tiket Button -->
                    <Link :href="route('tickets.index')" class="shrink-0">
                        <Button variant="outline" size="sm" class="h-9 text-xs whitespace-nowrap">
                            Lihat Semua Tiket <ArrowRight class="w-3.5 h-3.5 ml-1.5" />
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- ================= OPD USER DASHBOARD ================= -->
            <template v-if="role === 'opd_user'">
                <!-- 1. 4 SUMMARY STATS CARDS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- 1. Laporan Sedang Diproses -->
                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Sedang Diproses</CardTitle>
                            <Clock class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.in_process || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Aktif ditangani tim Kominfo</p>
                        </CardContent>
                    </Card>
                    
                    <!-- 2. Laporan Selesai -->
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Laporan Selesai</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.closed_tickets || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Kendala tuntas diperbaiki</p>
                        </CardContent>
                    </Card>

                    <!-- 3. Menunggu Penilaian / Rating -->
                    <Card class="border-yellow-200 bg-amber-50/30">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Menunggu Penilaian</CardTitle>
                            <Star class="h-4 w-4 text-amber-500 fill-amber-400" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.pending_rating || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Tiket selesai belum dinilai</p>
                        </CardContent>
                    </Card>
                    
                    <!-- 4. Total Seluruh Laporan -->
                    <Card class="border-slate-200 bg-slate-50/50 shadow-sm">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-slate-700">Total Seluruh Laporan</CardTitle>
                            <Ticket class="h-4 w-4 text-slate-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-slate-900">{{ stats.total_reports || 0 }}</div>
                            <p class="text-xs text-slate-500 mt-1">Riwayat laporan instansi Anda</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- 2. PENDING RATING NOTICE BANNER (CONDITIONAL) -->
                <div 
                    v-if="stats.pending_rating && stats.pending_rating > 0" 
                    class="p-4 rounded-xl border border-amber-200 bg-amber-50/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-xs"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0">
                            <Star class="w-5 h-5 text-amber-600 fill-amber-400" />
                        </div>
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-amber-950">Penilaian Kepuasan Layanan</h4>
                            <p class="text-xs text-amber-800 mt-0.5">
                                Terdapat <strong>{{ stats.pending_rating }} tiket</strong> yang telah selesai diperbaiki. Mohon berikan penilaian bintang & ulasan terhadap kinerja teknisi Kominfo.
                            </p>
                        </div>
                    </div>
                    <Link :href="route('tickets.index', { status: 'closed' })" class="shrink-0 w-full sm:w-auto">
                        <Button size="sm" class="w-full sm:w-auto text-xs bg-amber-600 hover:bg-amber-700 text-white font-semibold h-8 px-3">
                            Beri Ulasan Sekarang <ArrowRight class="w-3.5 h-3.5 ml-1.5" />
                        </Button>
                    </Link>
                </div>

                <!-- 3. MAIN 2-COLUMN GRID -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    
                    <!-- LEFT COLUMN (8 Cols): QUICK ACTION & RECENT TICKETS TABLE -->
                    <div class="lg:col-span-8 space-y-6">
                        <!-- Quick Action Card -->
                        <div class="p-4 sm:p-5 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50/80 via-blue-50/40 to-indigo-50/60 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">
                                        Aksi Cepat
                                    </span>
                                    <h3 class="text-sm sm:text-base font-bold text-slate-900">
                                        Mengalami Gangguan Jaringan / Internet?
                                    </h3>
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed max-w-xl">
                                    Laporkan kendala teknis (Fiber Optic, LAN, WiFi) kantor Anda secara langsung ke tim teknisi Diskominfo Palu untuk segera diverifikasi dan ditangani di lokasi.
                                </p>
                            </div>
                            <Button 
                                @click="openCreateTicketModal" 
                                class="bg-kominfo-primary hover:bg-kominfo-primary-dark text-white font-medium text-xs sm:text-sm h-9 px-4 shrink-0 w-full sm:w-auto shadow-xs"
                            >
                                <Plus class="w-4 h-4 mr-1.5" /> Buat Laporan Baru
                            </Button>
                        </div>

                        <!-- Recent Tickets Table -->
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                                <div>
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">
                                        Daftar Laporan Terkini Instansi
                                    </CardTitle>
                                    <CardDescription class="text-xs text-slate-500 mt-0.5">
                                        Pantau progres dan teknisi penanggung jawab kendala jaringan Anda
                                    </CardDescription>
                                </div>
                                <Link :href="route('tickets.index')">
                                    <Button variant="ghost" size="sm" class="h-8 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50 font-medium">
                                        Lihat Semua Tiket <ArrowRight class="w-3.5 h-3.5 ml-1" />
                                    </Button>
                                </Link>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader class="bg-slate-50/75">
                                            <TableRow class="hover:bg-transparent">
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[140px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 min-w-[180px]">Kendala Teknis</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10">Prioritas</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10">Status</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 min-w-[140px]">Teknisi</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Aksi</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <template v-if="recentTickets && recentTickets.length > 0">
                                                <TableRow 
                                                    v-for="ticket in recentTickets" 
                                                    :key="ticket.id"
                                                    class="hover:bg-slate-50/80 transition-colors"
                                                >
                                                    <TableCell class="py-3 pl-4 sm:pl-6">
                                                        <Link :href="route('tickets.show', ticket.id)" class="group block">
                                                            <div class="text-xs font-semibold text-blue-600 font-mono group-hover:underline">
                                                                {{ ticket.ticket_number }}
                                                            </div>
                                                            <div class="text-[11px] text-slate-400 mt-0.5 capitalize">
                                                                {{ (ticket.infrastructure_type || ticket.network_type) ? (ticket.infrastructure_type || ticket.network_type).replace('_', ' ') : '-' }}
                                                            </div>
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell class="py-3">
                                                        <Link :href="route('tickets.show', ticket.id)" class="block group">
                                                            <span class="text-xs font-medium text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1" :title="ticket.title">
                                                                {{ ticket.title }}
                                                            </span>
                                                            <span class="text-[11px] text-slate-400 block mt-0.5">{{ ticket.created_at_diff }}</span>
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs whitespace-nowrap">
                                                        <span :class="getPriorityColor(ticket.priority)">
                                                            {{ getPriorityLabel(ticket.priority) }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs whitespace-nowrap">
                                                        <span :class="getStatusColor(ticket.status)">
                                                            {{ getStatusLabel(ticket.status) }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs text-slate-700">
                                                        <span class="line-clamp-1" :title="ticket.technicians_label">
                                                            {{ ticket.technicians_label || '-' }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs text-right pr-4 sm:pr-6 whitespace-nowrap">
                                                        <Link :href="route('tickets.show', ticket.id)">
                                                            <Button variant="outline" size="sm" class="h-7 text-xs px-2.5 border-slate-200 hover:border-blue-500 hover:bg-blue-50/50 font-medium">
                                                                <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" /> Buka
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>
                                            </template>
                                            <TableRow v-else>
                                                <TableCell colspan="6" class="h-28 text-center text-xs text-slate-400">
                                                    Belum ada riwayat laporan tiket saat ini.
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- RIGHT COLUMN (4 Cols): HELPDESK INFO & CONTACT -->
                    <div class="lg:col-span-4 space-y-6">
                        <!-- Pusat Informasi & Lokasi Kantor -->
                        <Card class="border-slate-200 shadow-sm overflow-hidden">
                            <CardHeader class="pb-3 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <Headphones class="w-4 h-4 text-blue-600" />
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">
                                        Pusat Informasi & Lokasi Kantor
                                    </CardTitle>
                                </div>
                                <CardDescription class="text-xs text-slate-500">
                                    Dinas Komunikasi dan Informatika Kota Palu
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="pt-4 space-y-3.5">
                                <!-- Jam Operasional -->
                                <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-100 space-y-0.5">
                                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                                        <Clock class="w-3.5 h-3.5 text-slate-500" />
                                        <span>Jam Layanan Operasional</span>
                                    </div>
                                    <p class="text-xs text-slate-600 pl-5">
                                        Senin – Jumat: 08.00 – 16.00 WITA
                                    </p>
                                </div>

                                <!-- Alamat Kantor & Peta Interaktif -->
                                <div class="space-y-2">
                                    <div class="flex items-start gap-2 text-xs text-slate-700">
                                        <MapPin class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                                        <div class="leading-relaxed">
                                            <span class="font-bold text-slate-900 block">Kantor Diskominfo Kota Palu</span>
                                            <span>Jl. Balai Kota No. 1, Tanamodindi, Kec. Mantikulore, Kota Palu, Sulawesi Tengah 94118</span>
                                        </div>
                                    </div>

                                    <!-- Embedded Interactive Google Maps -->
                                    <div class="w-full h-48 rounded-lg overflow-hidden border border-slate-200 shadow-xs relative bg-slate-100 mt-2">
                                        <iframe
                                            src="https://maps.google.com/maps?q=-0.9053872,119.8941934&hl=id&z=17&output=embed"
                                            width="100%"
                                            height="100%"
                                            style="border: 0;"
                                            allowfullscreen=""
                                            loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"
                                            title="Lokasi Kantor Diskominfo Kota Palu"
                                        ></iframe>
                                    </div>

                                    <div class="pt-1 flex justify-end">
                                        <a 
                                            href="https://maps.google.com/?q=-0.9053872,119.8941934" 
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
                                        >
                                            <ExternalLink class="w-3.5 h-3.5" />
                                            <span>Buka Rute di Google Maps</span>
                                        </a>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Create Ticket Modal Dialog (OPD Quick Form) -->
                <Dialog v-model:open="isCreateModalOpen">
                    <DialogContent class="w-full h-full max-w-full max-h-full rounded-none top-0 left-0 translate-x-0 translate-y-0 p-4 sm:p-6 overflow-y-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:max-w-[650px] sm:max-h-[90vh] sm:h-auto sm:rounded-xl">
                        <DialogHeader class="pb-2 border-b border-slate-100 sm:border-none">
                            <DialogTitle class="text-lg sm:text-xl font-bold text-slate-900">Buat Laporan Tiket Gangguan</DialogTitle>
                            <DialogDescription class="text-xs sm:text-sm text-slate-500">
                                Lengkapi detail kendala jaringan kantor Anda untuk diteruskan ke tim teknisi Kominfo.
                            </DialogDescription>
                        </DialogHeader>

                        <form @submit.prevent="submitCreateTicket" class="space-y-4 pt-1 sm:pt-2">
                            <!-- Jenis Kendala / Subjek Laporan -->
                            <div>
                                <InputLabel value="Jenis Kendala / Subjek Laporan *" class="text-xs font-semibold text-slate-700" />
                                <Select :modelValue="selectedOpdIssueOption" @update:modelValue="onSelectOpdIssueOption">
                                    <SelectTrigger class="mt-1">
                                        <SelectValue placeholder="-- Pilih Jenis Kendala yang Dialami --" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem 
                                            v-for="opt in opdIssueOptions" 
                                            :key="opt.value" 
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="opdCreateForm.errors.title" class="mt-1" />

                                <!-- Text input appears ONLY when 'other' is selected -->
                                <div v-if="selectedOpdIssueOption === 'other'" class="mt-2">
                                    <Input 
                                        id="opd_title" 
                                        v-model="opdCreateForm.title" 
                                        placeholder="Tuliskan ringkasan kendala yang dialami..." 
                                        class="mt-1" 
                                    />
                                    <p class="text-[11px] text-slate-500 mt-1">Tuliskan ringkasan kendala secara singkat (minimal 5 karakter).</p>
                                </div>
                            </div>

                            <!-- Location Details -->
                            <div>
                                <InputLabel for="opd_location_details" value="Lokasi Detail / Ruangan *" />
                                <Input id="opd_location_details" v-model="opdCreateForm.location_details" placeholder="Cth: Gedung B Lantai 2, Ruang Rapat" class="mt-1" />
                                <InputError :message="opdCreateForm.errors.location_details" class="mt-1" />
                            </div>

                            <!-- Description -->
                            <div>
                                <InputLabel for="opd_description" value="Deskripsi Detail Kendala *" />
                                <Textarea 
                                    id="opd_description" 
                                    v-model="opdCreateForm.description" 
                                    rows="3" 
                                    placeholder="Jelaskan kendala apa yang dialami, sejak kapan, dan dampaknya..." 
                                    class="mt-1"
                                />
                                <InputError :message="opdCreateForm.errors.description" class="mt-1" />
                            </div>

                            <!-- Attachments -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <InputLabel value="Lampiran Bukti Foto" />
                                    <span class="text-xs text-slate-400 font-normal italic">(Opsional - Maks. 3 File)</span>
                                </div>
                                <p class="text-xs text-slate-500 mb-2 mt-0.5">Unggah foto perangkat atau pesan error jika ada.</p>
                                <FileUpload 
                                    v-model="opdCreateForm.attachments"
                                    :multiple="true"
                                    :maxFiles="3"
                                    :maxSizeMB="5"
                                    @error="(msg) => opdCreateForm.errors.attachments = msg"
                                />
                                <InputError :message="opdCreateForm.errors.attachments" class="mt-1" />
                            </div>

                            <DialogFooter class="pt-3 pb-2 border-t border-slate-100 sticky bottom-0 bg-white sm:static">
                                <Button type="button" variant="outline" @click="isCreateModalOpen = false">Batal</Button>
                                <Button type="submit" :disabled="opdCreateForm.processing" class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                                    {{ opdCreateForm.processing ? 'Mengirim Laporan...' : 'Kirim Laporan Gangguan' }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </template>

            <!-- ================= TECHNICIAN DASHBOARD ================= -->
            <template v-if="role === 'technician'">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- 1. Total Tiket Selesai -->
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Total Tiket Selesai</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.closed_tickets || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Seluruh penugasan tuntas</p>
                        </CardContent>
                    </Card>

                    <!-- 2. In Progress -->
                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">In Progress</CardTitle>
                            <Clock class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.in_progress || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Tugas aktif di lapangan</p>
                        </CardContent>
                    </Card>
                    
                    <!-- 3. Pending Approval -->
                    <Card class="border-purple-200 bg-purple-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-900">Pending Approval</CardTitle>
                            <ShieldCheck class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-950">{{ stats.pending_approval || 0 }}</div>
                            <p class="text-xs text-purple-700 mt-1">Menunggu review Admin</p>
                        </CardContent>
                    </Card>

                    <!-- 4. Rating Kepuasan -->
                    <Card class="border-yellow-200 bg-amber-50/30">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Rating Kepuasan</CardTitle>
                            <Star class="h-4 w-4 text-amber-500 fill-amber-400" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950 flex items-center gap-1.5">
                                <span v-if="stats.avg_rating && stats.avg_rating > 0">
                                    {{ stats.avg_rating }} <span class="text-sm font-normal text-slate-500">/ 5.0</span>
                                </span>
                                <span v-else class="text-slate-400 text-xl font-normal">-</span>
                            </div>
                            <p class="text-xs text-amber-700 mt-1">
                                {{ stats.rating_count ? `${stats.rating_count} ulasan diterima` : 'Belum ada ulasan' }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Section Grafik Performa: 1. Volume Tiket Selesai & 2. Rata-rata Waktu Penyelesaian -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                    <!-- 1. Volume Tiket Selesai Bulanan (Bar Chart) -->
                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                                <div>
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">
                                        Volume Tiket Selesai Bulanan
                                    </CardTitle>
                                    <CardDescription class="text-xs text-slate-500 mt-0.5">
                                        Jumlah tiket yang berhasil diselesaikan per bulan pada tahun {{ technicianResolutionChart?.year || '2025' }}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4 pb-2">
                            <div class="h-64 sm:h-72 w-full">
                                <Bar 
                                    :data="techVolumeChartData" 
                                    :options="techVolumeChartOptions" 
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- 2. Rata-rata Waktu Penyelesaian Tiket (Line Chart) -->
                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <Clock class="w-4 h-4 text-blue-600" />
                                <div>
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">
                                        Rata-rata Waktu Penyelesaian (Jam)
                                    </CardTitle>
                                    <CardDescription class="text-xs text-slate-500 mt-0.5">
                                        Tren kecepatan penanganan tiket selesai per bulan pada tahun {{ technicianResolutionChart?.year || '2025' }}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4 pb-2">
                            <div class="h-64 sm:h-72 w-full">
                                <Line 
                                    :data="techResolutionChartData" 
                                    :options="techResolutionChartOptions" 
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- 2-Column Formal Layout: Left (Active Tasks) & Right (OPD Feedbacks) -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">
                    <!-- Left Column: Daftar Tugas Aktif (In Progress) -->
                    <div class="lg:col-span-8 space-y-6">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                                <div>
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Daftar Tugas Aktif Lapangan</CardTitle>
                                    <CardDescription class="text-xs text-slate-500 mt-0.5">Tiket dalam penanganan yang ditugaskan kepada Anda</CardDescription>
                                </div>
                                <div>
                                    <Link :href="route('tickets.index')">
                                        <Button variant="outline" size="sm" class="h-8 text-xs text-slate-700 hover:text-slate-900 border-slate-200 hover:bg-slate-50 font-medium">
                                            Lihat Semua Tiket <ArrowRight class="w-3.5 h-3.5 ml-1" />
                                        </Button>
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent class="p-0">
                                <div class="overflow-x-auto">
                                    <Table>
                                        <TableHeader class="bg-slate-50">
                                            <TableRow class="hover:bg-transparent">
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[140px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10">Instansi (OPD)</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10">Kendala Teknis</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Prioritas</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Batas SLA</TableHead>
                                                <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Aksi</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <template v-if="activeTasks && activeTasks.length > 0">
                                                <TableRow 
                                                    v-for="task in activeTasks" 
                                                    :key="task.id"
                                                    class="hover:bg-slate-50/80 transition-colors"
                                                >
                                                    <TableCell class="py-3 pl-4 sm:pl-6">
                                                        <Link :href="route('tickets.show', task.id)" class="group block">
                                                            <div class="text-xs font-semibold text-blue-600 font-mono group-hover:underline">
                                                                {{ task.ticket_number }}
                                                            </div>
                                                            <div class="text-[11px] text-slate-400 mt-0.5 capitalize">
                                                                {{ (task.infrastructure_type || task.network_type) ? (task.infrastructure_type || task.network_type).replace('_', ' ') : '-' }}
                                                            </div>
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell class="py-3">
                                                        <span class="text-xs font-medium text-slate-900 block truncate max-w-[180px]" :title="task.department_name">
                                                            {{ task.department_name }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3">
                                                        <Link :href="route('tickets.show', task.id)" class="block group">
                                                            <span class="text-xs text-slate-800 group-hover:text-blue-600 transition-colors line-clamp-1" :title="task.title">
                                                                {{ task.title }}
                                                            </span>
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs text-center whitespace-nowrap">
                                                        <span 
                                                            class="font-semibold"
                                                            :class="{
                                                                'text-rose-600': task.priority === 'emergency',
                                                                'text-amber-600': task.priority === 'high',
                                                                'text-blue-600': task.priority === 'medium',
                                                                'text-slate-500': task.priority === 'low'
                                                            }"
                                                        >
                                                            {{ getPriorityLabel(task.priority) }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs text-center whitespace-nowrap">
                                                        <span 
                                                            :class="task.is_overdue ? 'text-red-600 font-medium' : 'text-slate-600'"
                                                        >
                                                            {{ task.due_human }}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell class="py-3 text-xs text-right pr-4 sm:pr-6">
                                                        <Link :href="route('tickets.show', task.id)">
                                                            <Button variant="outline" size="sm" class="h-7 text-xs px-2.5 border-slate-200 hover:bg-slate-100 hover:text-slate-900 font-medium">
                                                                Buka
                                                            </Button>
                                                        </Link>
                                                    </TableCell>
                                                </TableRow>
                                            </template>
                                            <TableRow v-else>
                                                <TableCell colspan="6" class="h-32 text-center text-xs text-slate-400">
                                                    <div class="flex flex-col items-center justify-center py-4">
                                                        <CheckCircle2 class="w-7 h-7 text-slate-300 mb-1.5 stroke-[1.5]" />
                                                        <p class="font-medium text-slate-700">Tidak ada tugas aktif di lapangan</p>
                                                        <p class="text-slate-400 text-[11px] mt-0.5">Seluruh tiket penugasan Anda saat ini telah tertangani.</p>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Ulasan Kepuasan OPD -->
                    <div class="lg:col-span-4 space-y-6">
                        <Card class="border-slate-200 shadow-sm">
                            <CardHeader class="pb-3 border-b border-slate-100">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Ulasan Kepuasan OPD</CardTitle>
                                    <span v-if="stats.avg_rating && stats.avg_rating > 0" class="text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                        ⭐ {{ stats.avg_rating }} / 5.0
                                    </span>
                                </div>
                                <CardDescription class="text-xs text-slate-500 mt-0.5">Penilaian dari pelapor setelah tiket diselesaikan</CardDescription>
                            </CardHeader>
                            <CardContent class="pt-3 pb-2 px-4 sm:px-5">
                                <div v-if="recentFeedbacks && recentFeedbacks.length > 0" class="divide-y divide-slate-100">
                                    <div 
                                        v-for="fb in recentFeedbacks" 
                                        :key="fb.id" 
                                        class="py-3.5 first:pt-0 last:pb-0"
                                    >
                                        <!-- Rating Stars & Time -->
                                        <div class="flex items-center justify-between gap-1">
                                            <div class="flex items-center gap-1">
                                                <Star 
                                                    v-for="i in 5" 
                                                    :key="i" 
                                                    class="w-3.5 h-3.5" 
                                                    :class="i <= fb.rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200 fill-slate-100'"
                                                />
                                                <span class="text-xs font-semibold text-slate-800 ml-1">{{ fb.rating }}.0</span>
                                            </div>
                                            <span class="text-[11px] text-slate-400">{{ fb.rated_at_diff }}</span>
                                        </div>

                                        <!-- Feedback Comment (Formal Quote) -->
                                        <div v-if="fb.feedback_comment" class="mt-2 text-xs text-slate-700 border-l-2 border-slate-300 pl-2.5 py-0.5 italic leading-relaxed">
                                            "{{ fb.feedback_comment }}"
                                        </div>
                                        <div v-else class="mt-1 text-[11px] text-slate-400 italic">
                                            (Tidak menyertakan catatan tertulis)
                                        </div>

                                        <!-- Meta Info: Department & Ticket Number -->
                                        <div class="flex items-center justify-between gap-2 mt-2 text-[11px] text-slate-500">
                                            <span class="font-medium text-slate-700 truncate max-w-[170px]" :title="fb.department_name">
                                                {{ fb.department_name }}
                                            </span>
                                            <Link :href="route('tickets.show', fb.id)" class="font-mono text-slate-500 hover:text-blue-600 hover:underline shrink-0">
                                                {{ fb.ticket_number }}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-8 text-xs text-slate-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <Star class="w-6 h-6 text-slate-300 mb-1.5 stroke-[1.5]" />
                                        <p class="font-medium text-slate-600">Belum ada ulasan</p>
                                        <p class="text-slate-400 text-[11px] mt-0.5">Ulasan dari OPD akan tampil di sini setelah tiket diselesaikan.</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </template>

            <!-- ================= ADMIN DASHBOARD ================= -->
            <template v-if="role === 'admin'">
                <!-- 6 Summary Metric Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                    <!-- 1. Total Tiket -->
                    <Card class="border-slate-200 bg-slate-50/50">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-slate-800">Total Tiket</CardTitle>
                            <Ticket class="h-4 w-4 text-slate-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-slate-950">{{ stats.total_tickets || 0 }}</div>
                            <p class="text-xs text-slate-500 mt-1">Laporan periode ini</p>
                        </CardContent>
                    </Card>

                    <!-- 2. Menunggu Verifikasi -->
                    <Card class="border-blue-200 bg-blue-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-blue-900">Menunggu Verifikasi</CardTitle>
                            <Clock class="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-950">{{ stats.pending_admin || 0 }}</div>
                            <p class="text-xs text-blue-700 mt-1">Laporan baru masuk</p>
                        </CardContent>
                    </Card>

                    <!-- 3. Dalam Penanganan -->
                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Dalam Penanganan</CardTitle>
                            <Activity class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.in_progress || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Sedang dikerjakan teknisi</p>
                        </CardContent>
                    </Card>

                    <!-- 4. Menunggu Approval -->
                    <Card class="border-purple-200 bg-purple-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-900">Menunggu Approval</CardTitle>
                            <ShieldCheck class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-950">{{ stats.pending_approval || 0 }}</div>
                            <p class="text-xs text-purple-700 mt-1">Pekerjaan siap di-QC</p>
                        </CardContent>
                    </Card>

                    <!-- 5. Selesai -->
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Selesai</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.closed_tickets || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Perbaikan tuntas & ditutup</p>
                        </CardContent>
                    </Card>

                    <!-- 6. Ditolak -->
                    <Card class="border-rose-200 bg-rose-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-rose-900">Ditolak</CardTitle>
                            <AlertTriangle class="h-4 w-4 text-rose-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-rose-950">{{ stats.rejected_tickets || 0 }}</div>
                            <p class="text-xs text-rose-700 mt-1">Laporan tidak valid</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Row 1: Tren Tiket Terselesaikan & Laporan Distribusi Infrastruktur -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Line Chart: Tren Tiket Terselesaikan -->
                    <Card class="border-slate-200 shadow-sm flex flex-col">
                        <CardHeader class="pb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <TrendingUp class="w-4 h-4 text-emerald-600" />
                                <CardTitle class="text-sm font-bold text-slate-900">Tren Tiket Terselesaikan</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-2 flex-1 flex flex-col justify-between">
                            <div class="h-56 sm:h-64 relative flex items-center justify-center">
                                <Line 
                                    v-if="ticketTrend && ticketTrend.labels && ticketTrend.labels.length > 0"
                                    :data="lineChartData" 
                                    :options="lineChartOptions" 
                                />
                                <div v-else class="h-full flex items-center justify-center text-xs text-slate-400">
                                    Belum ada data tren tiket pada periode ini
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Chart: Distribusi Berdasarkan Infrastruktur (Bar) -->
                    <Card class="border-slate-200 shadow-sm flex flex-col">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <Network class="w-4 h-4 text-indigo-600" />
                                <CardTitle class="text-sm font-bold text-slate-900">Laporan Distribusi Infrastruktur</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="flex-1 flex flex-col justify-between pt-2">
                            <div class="h-52 relative flex items-center justify-center">
                                <Bar 
                                    v-if="infraDist && infraDist.data.some(v => v > 0)"
                                    :data="barChartData" 
                                    :options="barChartOptions" 
                                />
                                <div v-else class="text-center text-xs text-slate-400">
                                    Belum ada data distribusi infrastruktur pada periode ini
                                </div>
                            </div>

                            <!-- Custom Quick Summary Pills -->
                            <div v-if="infraDist" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 mt-4 pt-3 border-t border-slate-100 text-xs text-center">
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 block truncate" title="Fiber optic">Fiber optic</span>
                                    <span class="font-bold text-sky-700 text-xs">{{ infraDist.data[0] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 block truncate" title="Perangkat/Akses">Perangkat/Akses</span>
                                    <span class="font-bold text-indigo-700 text-xs">{{ infraDist.data[1] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 block truncate" title="Power/poe">Power/poe</span>
                                    <span class="font-bold text-amber-700 text-xs">{{ infraDist.data[2] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 block truncate" title="Converter">Converter</span>
                                    <span class="font-bold text-pink-700 text-xs">{{ infraDist.data[3] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[10px] sm:text-[11px] text-slate-500 block truncate" title="Layanan/jaringan">Layanan/jaringan</span>
                                    <span class="font-bold text-emerald-700 text-xs">{{ infraDist.data[4] || 0 }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts Row 2: Status & Prioritas Tiket -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Chart 1: Distribusi Status Tiket (Doughnut) -->
                    <Card class="border-slate-200 shadow-sm flex flex-col">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <PieChart class="w-4 h-4 text-blue-600" />
                                <CardTitle class="text-sm font-bold text-slate-900">Distribusi Status Tiket</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="flex-1 flex flex-col justify-between pt-2">
                            <div class="h-52 relative flex items-center justify-center">
                                <Doughnut 
                                    v-if="statusDistribution && statusDistribution.data.some(v => v > 0)"
                                    :data="doughnutChartData" 
                                    :options="doughnutChartOptions" 
                                />
                                <div v-else class="text-center text-xs text-slate-400">
                                    Belum ada data status pada periode ini
                                </div>
                            </div>

                            <!-- Custom Legend with counts and percentages -->
                            <div v-if="statusDistribution" class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 text-xs">
                                <div 
                                    v-for="(label, idx) in statusDistribution.labels" 
                                    :key="label"
                                    class="flex items-center gap-1.5"
                                >
                                    <span 
                                        class="w-2.5 h-2.5 rounded-full shrink-0" 
                                        :style="{ backgroundColor: statusDistribution.colors[idx] }"
                                    />
                                    <div class="truncate">
                                        <span class="text-slate-600 text-[11px] block truncate">{{ label }}</span>
                                        <span class="font-bold text-slate-900 text-xs">
                                            {{ statusDistribution.data[idx] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Chart 2: Prioritas Tiket (Bar) -->
                    <Card class="border-slate-200 shadow-sm flex flex-col">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <Gauge class="w-4 h-4 text-amber-600" />
                                <CardTitle class="text-sm font-bold text-slate-900">Prioritas Tingkat Urgensi</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="flex-1 flex flex-col justify-between pt-2">
                            <div class="h-52 relative flex items-center justify-center">
                                <Bar 
                                    v-if="priorityDistribution && priorityDistribution.data.some(v => v > 0)"
                                    :data="priorityBarChartData" 
                                    :options="priorityBarChartOptions" 
                                />
                                <div v-else class="text-center text-xs text-slate-400">
                                    Belum ada data prioritas pada periode ini
                                </div>
                            </div>

                            <!-- Custom Quick Summary Pills for Priority -->
                            <div v-if="priorityDistribution" class="grid grid-cols-4 gap-1.5 mt-4 pt-3 border-t border-slate-100 text-xs text-center">
                                <div class="bg-slate-50 rounded-lg p-1.5 border border-slate-100">
                                    <span class="text-[10px] text-slate-500 block truncate">Rendah</span>
                                    <span class="font-bold text-slate-700 text-xs">{{ priorityDistribution.data[0] || 0 }}</span>
                                </div>
                                <div class="bg-blue-50/50 rounded-lg p-1.5 border border-blue-100">
                                    <span class="text-[10px] text-blue-600 block truncate">Sedang</span>
                                    <span class="font-bold text-blue-700 text-xs">{{ priorityDistribution.data[1] || 0 }}</span>
                                </div>
                                <div class="bg-amber-50/50 rounded-lg p-1.5 border border-amber-100">
                                    <span class="text-[10px] text-amber-600 block truncate">Tinggi</span>
                                    <span class="font-bold text-amber-700 text-xs">{{ priorityDistribution.data[2] || 0 }}</span>
                                </div>
                                <div class="bg-rose-50/50 rounded-lg p-1.5 border border-rose-100">
                                    <span class="text-[10px] text-rose-600 block truncate">Darurat</span>
                                    <span class="font-bold text-rose-700 text-xs">{{ priorityDistribution.data[3] || 0 }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Tiket Terbaru (Admin) -->
                <Card class="border-slate-200 shadow-sm mt-8">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Ticket class="w-4 h-4 text-blue-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Tiket Terbaru</CardTitle>
                        </div>
                        <div>
                            <Link :href="route('tickets.index')">
                                <Button variant="ghost" size="sm" class="h-8 text-xs text-blue-600 hover:text-blue-700 hover:bg-blue-50 font-medium">
                                    Lihat Semua Tiket <ArrowRight class="w-3.5 h-3.5 ml-1" />
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader class="bg-slate-50/75">
                                    <TableRow class="hover:bg-transparent">
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[160px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Judul Masalah</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Instansi (OPD)</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Prioritas</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Status</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="recentTickets && recentTickets.length > 0">
                                        <TableRow 
                                            v-for="ticket in recentTickets" 
                                            :key="ticket.id"
                                            class="hover:bg-slate-50/80 transition-colors"
                                        >
                                            <TableCell class="py-3 pl-4 sm:pl-6">
                                                <Link :href="route('tickets.show', ticket.id)" class="group block">
                                                    <div class="text-xs font-semibold text-blue-600 font-mono group-hover:underline">
                                                        {{ ticket.ticket_number }}
                                                    </div>
                                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ ticket.created_at }}</div>
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3">
                                                <Link :href="route('tickets.show', ticket.id)" class="block group">
                                                    <span class="text-xs font-medium text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1" :title="ticket.title">
                                                        {{ ticket.title }}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-slate-700">
                                                <span class="font-medium text-slate-900 block truncate max-w-[180px]">{{ ticket.department_name }}</span>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-center">
                                                <span :class="getPriorityColor(ticket.priority)">
                                                    {{ getPriorityLabel(ticket.priority) }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-center">
                                                <span :class="getStatusColor(ticket.status)">
                                                    {{ getStatusLabel(ticket.status) }}
                                                </span>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-right pr-4 sm:pr-6">
                                                <Link :href="route('tickets.show', ticket.id)">
                                                    <Button variant="outline" size="sm" class="h-7 text-xs px-2.5 border-slate-200 hover:border-blue-500 hover:bg-blue-50/50">
                                                        <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" /> Detail
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else>
                                        <TableCell colspan="6" class="h-28 text-center text-xs text-slate-400">
                                            Belum ada riwayat tiket terbaru saat ini.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Aktivitas Terbaru (Admin) -->
                <Card class="border-slate-200 shadow-sm mt-8">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Activity class="w-4 h-4 text-emerald-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Aktivitas Terbaru</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4 pb-2 px-4 sm:px-6">
                        <div v-if="recentActivities && recentActivities.length > 0" class="divide-y divide-slate-100">
                            <div 
                                v-for="act in recentActivities" 
                                :key="act.id" 
                                class="py-3.5 first:pt-0 last:pb-0"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-1 text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-semibold text-slate-900">{{ act.user_name }}</span>
                                        <span class="text-slate-300 font-light">•</span>
                                        <span :class="getRoleColor(act.user_role)" class="font-medium">
                                            {{ getRoleLabel(act.user_role) }}
                                        </span>
                                        <span class="text-slate-300 font-light">•</span>
                                        <Link :href="route('tickets.show', act.ticket_id)" class="font-mono text-blue-600 hover:underline font-medium">
                                            {{ act.ticket_number }}
                                        </Link>
                                    </div>
                                    <span class="text-[11px] text-slate-400 font-normal whitespace-nowrap">
                                        {{ act.created_at_diff }}
                                    </span>
                                </div>

                                <div v-if="act.comment" class="mt-1.5 px-3 py-1.5 bg-slate-50 border border-slate-100 rounded-md text-xs text-slate-600 leading-relaxed italic">
                                    "{{ act.comment }}"
                                </div>

                                <div class="flex items-center gap-2 mt-2 text-[11px]">
                                    <span class="text-slate-400">Status:</span>
                                    <span :class="getStatusColor(act.new_status)" class="font-semibold">
                                        {{ getStatusLabel(act.new_status) }}
                                    </span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-300 font-light">•</span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-500 font-medium truncate">
                                        {{ act.department_name }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-xs text-slate-400">
                            Belum ada riwayat aktivitas terbaru saat ini.
                        </div>
                    </CardContent>
                </Card>

                <!-- Monthly Performance & Resolution Summary Table Component -->
                <div class="mt-8">
                    <Card class="border-slate-200 shadow-sm overflow-hidden bg-white rounded-xl">
                        <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 gap-3 bg-slate-50/60 border-b border-slate-100 px-6 py-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-kominfo-primary">
                                        <BarChart3 class="w-4 h-4" />
                                    </div>
                                    <CardTitle class="text-base font-bold text-slate-900">Ringkasan Kinerja & Tingkat Penyelesaian Bulanan</CardTitle>
                                </div>
                                <CardDescription class="text-xs text-slate-500">
                                    Rekapitulasi kuantitas tiket, status penanganan, rata-rata durasi penyelesaian, dan efektivitas per bulan.
                                </CardDescription>
                            </div>
                            <div class="shrink-0">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-white text-slate-700 border border-slate-200 shadow-2xs">
                                    <Calendar class="w-3.5 h-3.5 text-slate-500" />
                                    <span>Periode: {{ activeFilterLabel }}</span>
                                </span>
                            </div>
                        </CardHeader>

                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader class="bg-slate-50/90 border-b border-slate-200">
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead class="font-bold text-slate-800 text-xs py-3.5 pl-6">Bulan</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3.5">Total Tiket</TableHead>
                                            <TableHead class="text-center font-bold text-amber-800 text-xs py-3.5">Diproses</TableHead>
                                            <TableHead class="text-center font-bold text-emerald-800 text-xs py-3.5">Selesai</TableHead>
                                            <TableHead class="text-center font-bold text-rose-800 text-xs py-3.5">Ditolak / Batal</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3.5">Rata-rata Waktu Penanganan</TableHead>
                                            <TableHead class="font-bold text-slate-800 text-xs py-3.5 pr-6 min-w-[200px]">Tingkat Penyelesaian</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        <template v-if="monthlyReports.length > 0">
                                            <TableRow 
                                                v-for="row in monthlyReports" 
                                                :key="row.period"
                                                class="hover:bg-slate-50/70 transition-colors border-b border-slate-100 text-xs"
                                            >
                                                <!-- 1. Bulan / Periode -->
                                                <TableCell class="font-semibold text-slate-900 py-3.5 pl-6 whitespace-nowrap">
                                                    {{ row.month_name }}
                                                </TableCell>

                                                <!-- 2. Total Tiket -->
                                                <TableCell class="text-center py-3.5">
                                                    <span class="inline-block px-2.5 py-0.5 rounded-md bg-slate-100 font-bold text-slate-900 text-xs">
                                                        {{ row.total_tickets }}
                                                    </span>
                                                </TableCell>

                                                <!-- 3. Diproses (In Progress + Pending) -->
                                                <TableCell class="text-center py-3.5">
                                                    <span 
                                                        class="text-xs font-semibold"
                                                        :class="row.in_progress > 0 ? 'text-amber-600 font-bold' : 'text-slate-400'"
                                                    >
                                                        {{ row.in_progress }}
                                                    </span>
                                                </TableCell>

                                                <!-- 4. Selesai (Closed) -->
                                                <TableCell class="text-center py-3.5">
                                                    <span 
                                                        class="text-xs font-semibold"
                                                        :class="row.closed > 0 ? 'text-emerald-600 font-bold' : 'text-slate-400'"
                                                    >
                                                        {{ row.closed }}
                                                    </span>
                                                </TableCell>

                                                <!-- 5. Ditolak / Dibatalkan -->
                                                <TableCell class="text-center py-3.5">
                                                    <span 
                                                        class="text-xs font-semibold"
                                                        :class="row.cancelled > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'"
                                                    >
                                                        {{ row.cancelled }}
                                                    </span>
                                                </TableCell>

                                                <!-- 6. Rata-rata Waktu Penyelesaian -->
                                                <TableCell class="text-center text-slate-700 py-3.5 whitespace-nowrap">
                                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                                        <Clock class="w-3.5 h-3.5 text-slate-400" />
                                                        {{ row.avg_resolution_time || '-' }}
                                                    </span>
                                                </TableCell>

                                                <!-- 7. Tingkat Penyelesaian (Status Bar + Persentase) -->
                                                <TableCell class="py-3.5 pr-6">
                                                    <div class="flex items-center gap-3">
                                                        <!-- Horizontal Progress Bar -->
                                                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden min-w-[70px]">
                                                            <div 
                                                                class="h-full rounded-full transition-all duration-500"
                                                                :class="getProgressBg(row.completion_rate)"
                                                                :style="{ width: `${Math.min(100, Math.max(0, row.completion_rate))}%` }"
                                                            />
                                                        </div>
                                                        <!-- Percentage Label -->
                                                        <span 
                                                            class="text-xs font-mono font-bold w-14 text-right shrink-0"
                                                            :class="getRateTextColor(row.completion_rate)"
                                                        >
                                                            {{ Number(row.completion_rate || 0).toFixed(2) }}%
                                                        </span>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </template>

                                        <TableRow v-else>
                                            <TableCell colspan="7" class="text-center py-12 text-slate-500 text-xs">
                                                <div class="flex flex-col items-center justify-center space-y-2">
                                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                                        <BarChart3 class="w-5 h-5" />
                                                    </div>
                                                    <p class="font-semibold text-slate-700">Tidak ada data rekapitulasi tiket pada periode ini</p>
                                                    <p class="text-slate-400 text-[11px]">Silakan ubah filter periode atau rentang tanggal pada panel di atas.</p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>

                                    <!-- Table Summary Footer (Baris TOTAL) -->
                                    <TableFooter v-if="monthlySummary" class="bg-slate-100/90 border-t-2 border-slate-300 font-bold text-xs">
                                        <TableRow class="hover:bg-slate-100 font-bold">
                                            <!-- Total Label -->
                                            <TableCell class="py-4 pl-6 font-extrabold text-slate-950 uppercase tracking-wider">
                                                TOTAL KESELURUHAN
                                            </TableCell>

                                            <!-- Total Tiket -->
                                            <TableCell class="text-center py-4">
                                                <span class="inline-block px-2.5 py-0.5 rounded-md bg-slate-200/80 font-extrabold text-slate-950 text-xs">
                                                    {{ monthlySummary.total_tickets }}
                                                </span>
                                            </TableCell>

                                            <!-- Total Diproses -->
                                            <TableCell class="text-center py-4">
                                                <span class="text-xs font-extrabold text-amber-700">
                                                    {{ monthlySummary.in_progress }}
                                                </span>
                                            </TableCell>

                                            <!-- Total Selesai -->
                                            <TableCell class="text-center py-4">
                                                <span class="text-xs font-extrabold text-emerald-700">
                                                    {{ monthlySummary.closed }}
                                                </span>
                                            </TableCell>

                                            <!-- Total Ditolak / Batal -->
                                            <TableCell class="text-center py-4">
                                                <span class="text-xs font-extrabold text-rose-700">
                                                    {{ monthlySummary.cancelled }}
                                                </span>
                                            </TableCell>

                                            <!-- Overall Average Resolution Time -->
                                            <TableCell class="text-center text-slate-900 py-4 font-bold whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1.5 font-bold">
                                                    <Clock class="w-3.5 h-3.5 text-slate-600" />
                                                    {{ monthlySummary.avg_resolution_time || '-' }}
                                                </span>
                                            </TableCell>

                                            <!-- Overall Completion Rate Bar -->
                                            <TableCell class="py-4 pr-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex-1 bg-slate-200 rounded-full h-2.5 overflow-hidden min-w-[70px]">
                                                        <div 
                                                            class="h-full rounded-full transition-all duration-500"
                                                            :class="getProgressBg(monthlySummary.completion_rate)"
                                                            :style="{ width: `${Math.min(100, Math.max(0, monthlySummary.completion_rate))}%` }"
                                                        />
                                                    </div>
                                                    <span 
                                                        class="text-xs font-mono font-extrabold w-14 text-right shrink-0"
                                                        :class="getRateTextColor(monthlySummary.completion_rate)"
                                                    >
                                                        {{ Number(monthlySummary.completion_rate || 0).toFixed(2) }}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableFooter>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
