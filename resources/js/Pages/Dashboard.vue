<script setup lang="ts">
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, Link, router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableHeader,
  TableBody,
  TableFooter,
  TableRow,
  TableHead,
  TableCell,
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
  TrendingUp
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
    network_type: string;
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
    networkTypeDistribution?: ChartDataset | null;
    priorityDistribution?: ChartDataset | null;
    ticketTrend?: TicketTrendDataset | null;
    recentTickets?: RecentTicket[];
    recentActivities?: RecentActivity[];
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
    networkTypeDistribution: null,
    priorityDistribution: null,
    ticketTrend: null,
    recentTickets: () => [],
    recentActivities: () => [],
});

const user = computed(() => usePage().props.auth.user as any);
const role = computed(() => user.value?.role);

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
        case 'fiber_optic': return 'FO';
        case 'lan': return 'LAN';
        case 'wifi': return 'WiFi';
        default: return type || '-';
    }
};

const getNetworkColor = (type: string) => {
    switch (type) {
        case 'fiber_optic': return 'text-sky-600 font-semibold';
        case 'lan': return 'text-indigo-600 font-semibold';
        case 'wifi': return 'text-teal-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

const getPriorityLabel = (priority: string) => {
    switch (priority) {
        case 'low': return 'Low';
        case 'medium': return 'Med';
        case 'high': return 'High';
        case 'emergency': return 'Emerg';
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

// Chart 3: Network Type Distribution (Bar)
const barChartData = computed(() => {
    if (!props.networkTypeDistribution) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: props.networkTypeDistribution.labels,
        datasets: [
            {
                label: 'Jumlah Laporan Gangguan',
                data: props.networkTypeDistribution.data,
                backgroundColor: props.networkTypeDistribution.colors,
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
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card class="border-blue-200 bg-blue-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-blue-900">Laporan Diproses</CardTitle>
                            <Activity class="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-blue-950">{{ stats.in_process || 0 }}</div>
                            <p class="text-xs text-blue-700 mt-1">Sedang diverifikasi atau ditangani tim</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Laporan Selesai</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.closed_tickets || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Perbaikan selesai & ditutup resmi</p>
                        </CardContent>
                    </Card>

                    <Card class="border-rose-200 bg-rose-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-rose-900">Perlu Perbaikan</CardTitle>
                            <AlertTriangle class="h-4 w-4 text-rose-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-rose-950">{{ stats.needs_fix || 0 }}</div>
                            <p class="text-xs text-rose-700 mt-1">Ditolak & dalam batas 72 jam</p>
                        </CardContent>
                    </Card>
                    
                    <Card>
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

                <!-- Tiket Terbaru (OPD) -->
                <Card class="border-slate-200 shadow-sm mt-6">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Ticket class="w-4 h-4 text-blue-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Tiket Terbaru Instansi</CardTitle>
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
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[140px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Judul & Masalah</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Jaringan</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Prioritas</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Status</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Waktu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="recentTickets && recentTickets.length > 0">
                                        <TableRow 
                                            v-for="ticket in recentTickets" 
                                            :key="ticket.id"
                                            class="hover:bg-slate-50/80 transition-colors"
                                        >
                                            <TableCell class="py-3 text-xs font-semibold text-blue-600 font-mono pl-4 sm:pl-6">
                                                <Link :href="route('tickets.show', ticket.id)" class="hover:underline">
                                                    {{ ticket.ticket_number }}
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3">
                                                <Link :href="route('tickets.show', ticket.id)" class="block group">
                                                    <span class="text-xs font-medium text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                                                        {{ ticket.title }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-400 block truncate mt-0.5">
                                                        {{ ticket.category_name }}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-center">
                                                <span :class="getNetworkColor(ticket.network_type)">
                                                    {{ getNetworkLabel(ticket.network_type) }}
                                                </span>
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
                                            <TableCell class="py-3 text-xs text-right whitespace-nowrap pr-4 sm:pr-6">
                                                <span class="text-slate-700 block font-medium">{{ ticket.created_at_diff }}</span>
                                                <span class="text-[10px] text-slate-400 block">{{ ticket.created_at }}</span>
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

                <!-- Aktivitas Terbaru (OPD) -->
                <Card class="border-slate-200 shadow-sm mt-6">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Activity class="w-4 h-4 text-emerald-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Aktivitas Terbaru</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4 pb-2 px-4 sm:px-6">
                        <div v-if="recentActivities && recentActivities.length > 0" class="space-y-3.5">
                            <div 
                                v-for="act in recentActivities" 
                                :key="act.id" 
                                class="pb-3.5 border-b border-slate-100 last:border-0 last:pb-0"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-1 text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-900">{{ act.user_name }}</span>
                                        <span class="text-slate-300">•</span>
                                        <span :class="getRoleColor(act.user_role)">
                                            {{ getRoleLabel(act.user_role) }}
                                        </span>
                                        <span class="text-slate-300">•</span>
                                        <Link :href="route('tickets.show', act.ticket_id)" class="font-mono text-blue-600 hover:underline font-medium">
                                            {{ act.ticket_number }}
                                        </Link>
                                    </div>
                                    <span class="text-[11px] text-slate-400 whitespace-nowrap">
                                        {{ act.created_at_diff }}
                                    </span>
                                </div>

                                <p v-if="act.comment" class="text-xs text-slate-600 mt-1 leading-relaxed">
                                    {{ act.comment }}
                                </p>

                                <div class="flex items-center gap-2 mt-1.5 text-[11px]">
                                    <span class="text-slate-400">Status:</span>
                                    <span :class="getStatusColor(act.new_status)">
                                        {{ getStatusLabel(act.new_status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-xs text-slate-400">
                            Belum ada riwayat aktivitas terbaru saat ini.
                        </div>
                    </CardContent>
                </Card>
            </template>

            <!-- ================= TECHNICIAN DASHBOARD ================= -->
            <template v-if="role === 'technician'">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <Card class="border-amber-200 bg-amber-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-amber-900">Tugas Tim Saya (In Progress)</CardTitle>
                            <Clock class="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-amber-950">{{ stats.my_team_tickets || 0 }}</div>
                            <p class="text-xs text-amber-700 mt-1">Tiket aktif ditugaskan ke Anda / Tim</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-purple-200 bg-purple-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-purple-900">Menunggu Review Admin</CardTitle>
                            <ShieldCheck class="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-purple-950">{{ stats.pending_approval || 0 }}</div>
                            <p class="text-xs text-purple-700 mt-1">Pekerjaan lapangan telah diajukan</p>
                        </CardContent>
                    </Card>
                    
                    <Card class="border-emerald-200 bg-emerald-50/40">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-xs sm:text-sm font-semibold text-emerald-900">Tuntas Bulan Ini</CardTitle>
                            <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-emerald-950">{{ stats.resolved_this_month || 0 }}</div>
                            <p class="text-xs text-emerald-700 mt-1">Tiket terverifikasi selesai</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Tiket Terbaru (Teknisi) -->
                <Card class="border-slate-200 shadow-sm mt-6">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Ticket class="w-4 h-4 text-amber-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Tiket Penugasan Terbaru</CardTitle>
                        </div>
                        <div>
                            <Link :href="route('tickets.index')">
                                <Button variant="ghost" size="sm" class="h-8 text-xs text-amber-600 hover:text-amber-700 hover:bg-amber-50 font-medium">
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
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[140px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Judul & Masalah</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Instansi (OPD)</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Jaringan</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Prioritas</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Status</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Waktu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="recentTickets && recentTickets.length > 0">
                                        <TableRow 
                                            v-for="ticket in recentTickets" 
                                            :key="ticket.id"
                                            class="hover:bg-slate-50/80 transition-colors"
                                        >
                                            <TableCell class="py-3 text-xs font-semibold text-amber-600 font-mono pl-4 sm:pl-6">
                                                <Link :href="route('tickets.show', ticket.id)" class="hover:underline">
                                                    {{ ticket.ticket_number }}
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3">
                                                <Link :href="route('tickets.show', ticket.id)" class="block group">
                                                    <span class="text-xs font-medium text-slate-900 group-hover:text-amber-600 transition-colors line-clamp-1">
                                                        {{ ticket.title }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-400 block truncate mt-0.5">
                                                        {{ ticket.category_name }}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-slate-700">
                                                <span class="font-medium text-slate-900 block truncate max-w-[180px]">{{ ticket.department_name }}</span>
                                                <span class="text-[10px] text-slate-400 uppercase font-mono">{{ ticket.reporter_name }}</span>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-center">
                                                <span :class="getNetworkColor(ticket.network_type)">
                                                    {{ getNetworkLabel(ticket.network_type) }}
                                                </span>
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
                                            <TableCell class="py-3 text-xs text-right whitespace-nowrap pr-4 sm:pr-6">
                                                <span class="text-slate-700 block font-medium">{{ ticket.created_at_diff }}</span>
                                                <span class="text-[10px] text-slate-400 block">{{ ticket.created_at }}</span>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else>
                                        <TableCell colspan="7" class="h-28 text-center text-xs text-slate-400">
                                            Belum ada tiket tugas aktif saat ini.
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Aktivitas Terbaru (Teknisi) -->
                <Card class="border-slate-200 shadow-sm mt-6">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 gap-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                            <Activity class="w-4 h-4 text-amber-600" />
                            <CardTitle class="text-sm sm:text-base font-bold text-slate-900">Aktivitas Penanganan Terbaru</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4 pb-2 px-4 sm:px-6">
                        <div v-if="recentActivities && recentActivities.length > 0" class="space-y-3.5">
                            <div 
                                v-for="act in recentActivities" 
                                :key="act.id" 
                                class="pb-3.5 border-b border-slate-100 last:border-0 last:pb-0"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-1 text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-900">{{ act.user_name }}</span>
                                        <span class="text-slate-300">•</span>
                                        <span :class="getRoleColor(act.user_role)">
                                            {{ getRoleLabel(act.user_role) }}
                                        </span>
                                        <span class="text-slate-300">•</span>
                                        <Link :href="route('tickets.show', act.ticket_id)" class="font-mono text-amber-600 hover:underline font-medium">
                                            {{ act.ticket_number }}
                                        </Link>
                                    </div>
                                    <span class="text-[11px] text-slate-400 whitespace-nowrap">
                                        {{ act.created_at_diff }}
                                    </span>
                                </div>

                                <p v-if="act.comment" class="text-xs text-slate-600 mt-1 leading-relaxed">
                                    {{ act.comment }}
                                </p>

                                <div class="flex items-center gap-2 mt-1.5 text-[11px]">
                                    <span class="text-slate-400">Status:</span>
                                    <span :class="getStatusColor(act.new_status)">
                                        {{ getStatusLabel(act.new_status) }}
                                    </span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-300">|</span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-500 truncate">
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

                <!-- Line Chart: Tren Tiket Terselesaikan -->
                <Card class="border-slate-200 shadow-sm flex flex-col">
                    <CardHeader class="pb-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <TrendingUp class="w-4 h-4 text-emerald-600" />
                            <CardTitle class="text-sm font-bold text-slate-900">Tren Tiket Terselesaikan</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-2">
                        <div class="h-60 relative">
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

                <!-- 3 Visual Interactive Charts Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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

                    <!-- Chart 3: Distribusi Berdasarkan Tipe Jaringan (Bar) -->
                    <Card class="border-slate-200 shadow-sm flex flex-col">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <Network class="w-4 h-4 text-indigo-600" />
                                <CardTitle class="text-sm font-bold text-slate-900">Laporan Tipe Jaringan</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="flex-1 flex flex-col justify-between pt-2">
                            <div class="h-52 relative flex items-center justify-center">
                                <Bar 
                                    v-if="networkTypeDistribution && networkTypeDistribution.data.some(v => v > 0)"
                                    :data="barChartData" 
                                    :options="barChartOptions" 
                                />
                                <div v-else class="text-center text-xs text-slate-400">
                                    Belum ada data tipe jaringan pada periode ini
                                </div>
                            </div>

                            <!-- Custom Quick Summary Pills -->
                            <div v-if="networkTypeDistribution" class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-slate-100 text-xs text-center">
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[11px] text-slate-500 block truncate">Fiber Optic</span>
                                    <span class="font-bold text-sky-700 text-xs">{{ networkTypeDistribution.data[0] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[11px] text-slate-500 block truncate">LAN</span>
                                    <span class="font-bold text-indigo-700 text-xs">{{ networkTypeDistribution.data[1] || 0 }}</span>
                                </div>
                                <div class="bg-slate-50 rounded-lg p-2 border border-slate-100">
                                    <span class="text-[11px] text-slate-500 block truncate">Wireless</span>
                                    <span class="font-bold text-teal-700 text-xs">{{ networkTypeDistribution.data[2] || 0 }}</span>
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
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 w-[140px] pl-4 sm:pl-6">No. Tiket</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Judul & Masalah</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10">Instansi (OPD)</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Jaringan</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Prioritas</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-center">Status</TableHead>
                                        <TableHead class="text-xs font-semibold text-slate-700 h-10 text-right pr-4 sm:pr-6">Waktu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <template v-if="recentTickets && recentTickets.length > 0">
                                        <TableRow 
                                            v-for="ticket in recentTickets" 
                                            :key="ticket.id"
                                            class="hover:bg-slate-50/80 transition-colors"
                                        >
                                            <TableCell class="py-3 text-xs font-semibold text-blue-600 font-mono pl-4 sm:pl-6">
                                                <Link :href="route('tickets.show', ticket.id)" class="hover:underline">
                                                    {{ ticket.ticket_number }}
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3">
                                                <Link :href="route('tickets.show', ticket.id)" class="block group">
                                                    <span class="text-xs font-medium text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                                                        {{ ticket.title }}
                                                    </span>
                                                    <span class="text-[11px] text-slate-400 block truncate mt-0.5">
                                                        {{ ticket.category_name }}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-slate-700">
                                                <span class="font-medium text-slate-900 block truncate max-w-[180px]">{{ ticket.department_name }}</span>
                                                <span class="text-[10px] text-slate-400 uppercase font-mono">{{ ticket.reporter_name }}</span>
                                            </TableCell>
                                            <TableCell class="py-3 text-xs text-center">
                                                <span :class="getNetworkColor(ticket.network_type)">
                                                    {{ getNetworkLabel(ticket.network_type) }}
                                                </span>
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
                                            <TableCell class="py-3 text-xs text-right whitespace-nowrap pr-4 sm:pr-6">
                                                <span class="text-slate-700 block font-medium">{{ ticket.created_at_diff }}</span>
                                                <span class="text-[10px] text-slate-400 block">{{ ticket.created_at }}</span>
                                            </TableCell>
                                        </TableRow>
                                    </template>
                                    <TableRow v-else>
                                        <TableCell colspan="7" class="h-28 text-center text-xs text-slate-400">
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
                        <div v-if="recentActivities && recentActivities.length > 0" class="space-y-3.5">
                            <div 
                                v-for="act in recentActivities" 
                                :key="act.id" 
                                class="pb-3.5 border-b border-slate-100 last:border-0 last:pb-0"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-1 text-xs">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="font-bold text-slate-900">{{ act.user_name }}</span>
                                        <span class="text-slate-300">•</span>
                                        <span :class="getRoleColor(act.user_role)">
                                            {{ getRoleLabel(act.user_role) }}
                                        </span>
                                        <span class="text-slate-300">•</span>
                                        <Link :href="route('tickets.show', act.ticket_id)" class="font-mono text-blue-600 hover:underline font-medium">
                                            {{ act.ticket_number }}
                                        </Link>
                                    </div>
                                    <span class="text-[11px] text-slate-400 whitespace-nowrap">
                                        {{ act.created_at_diff }}
                                    </span>
                                </div>

                                <p v-if="act.comment" class="text-xs text-slate-600 mt-1 leading-relaxed">
                                    {{ act.comment }}
                                </p>

                                <div class="flex items-center gap-2 mt-1.5 text-[11px]">
                                    <span class="text-slate-400">Status:</span>
                                    <span :class="getStatusColor(act.new_status)">
                                        {{ getStatusLabel(act.new_status) }}
                                    </span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-300">|</span>
                                    <span v-if="act.department_name && act.department_name !== '-'" class="text-slate-500 truncate">
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
                    <Card class="border-slate-200 shadow-sm overflow-hidden">
                        <CardHeader class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-4 gap-3 bg-slate-50/40 border-b border-slate-100">
                            <div class="flex items-center gap-2">
                                <BarChart3 class="w-5 h-5 text-slate-700" />
                                <CardTitle class="text-base font-bold text-slate-900">Ringkasan Kinerja & Tingkat Penyelesaian Bulanan</CardTitle>
                            </div>
                        </CardHeader>

                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader class="bg-slate-50/80">
                                        <TableRow class="hover:bg-transparent">
                                            <TableHead class="font-bold text-slate-800 text-xs py-3 pl-6">Bulan</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3">Total Tiket</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3">Selesai</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3">Ditolak</TableHead>
                                            <TableHead class="text-center font-bold text-slate-800 text-xs py-3">Rata-rata Waktu Penyelesaian</TableHead>
                                            <TableHead class="font-bold text-slate-800 text-xs py-3 pr-6 min-w-[200px]">Tingkat Penyelesaian</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        <template v-if="monthlyReports.length > 0">
                                            <TableRow 
                                                v-for="row in monthlyReports" 
                                                :key="row.period"
                                                class="hover:bg-slate-50/60 transition-colors border-b border-slate-100 text-xs"
                                            >
                                                <!-- 1. Bulan / Periode -->
                                                <TableCell class="font-medium text-slate-900 py-3.5 pl-6 whitespace-nowrap">
                                                    {{ row.month_name }}
                                                </TableCell>

                                                <!-- 2. Total Tiket -->
                                                <TableCell class="text-center font-bold text-slate-900 py-3.5">
                                                    {{ row.total_tickets }}
                                                </TableCell>

                                                <!-- 3. Selesai -->
                                                <TableCell class="text-center py-3.5">
                                                    <span 
                                                        class="text-xs font-semibold"
                                                        :class="row.closed > 0 ? 'text-emerald-600' : 'text-slate-400'"
                                                    >
                                                        {{ row.closed }}
                                                    </span>
                                                </TableCell>

                                                <!-- 4. Ditolak -->
                                                <TableCell class="text-center py-3.5">
                                                    <span 
                                                        class="text-xs font-semibold"
                                                        :class="row.cancelled > 0 ? 'text-rose-600' : 'text-slate-400'"
                                                    >
                                                        {{ row.cancelled }}
                                                    </span>
                                                </TableCell>

                                                <!-- 5. Rata-rata Waktu Penyelesaian -->
                                                <TableCell class="text-center text-slate-700 py-3.5 whitespace-nowrap">
                                                    <span class="inline-flex items-center gap-1">
                                                        <Clock class="w-3.5 h-3.5 text-slate-400" />
                                                        {{ row.avg_resolution_time }}
                                                    </span>
                                                </TableCell>

                                                <!-- 6. Tingkat Penyelesaian (Status Bar + Persentase) -->
                                                <TableCell class="py-3.5 pr-6">
                                                    <div class="flex items-center gap-3">
                                                        <!-- Horizontal Progress / Status Bar -->
                                                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden min-w-[70px]">
                                                            <div 
                                                                class="h-full rounded-full transition-all duration-500"
                                                                :class="getProgressBg(row.completion_rate)"
                                                                :style="{ width: `${Math.min(100, Math.max(0, row.completion_rate))}%` }"
                                                            />
                                                        </div>
                                                        <!-- Percentage Label -->
                                                        <span 
                                                            class="text-xs font-mono w-14 text-right shrink-0"
                                                            :class="getRateTextColor(row.completion_rate)"
                                                        >
                                                            {{ row.completion_rate.toFixed(2) }}%
                                                        </span>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        </template>

                                        <TableRow v-else>
                                            <TableCell colspan="6" class="text-center py-8 text-slate-500 text-xs">
                                                Tidak ada data rekapitulasi tiket pada periode ini.
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>

                                    <!-- Table Summary Footer (Baris TOTAL) -->
                                    <TableFooter v-if="monthlySummary" class="bg-slate-100/80 border-t-2 border-slate-300 font-bold text-xs">
                                        <TableRow class="hover:bg-slate-100/90 font-bold">
                                            <!-- Total Label -->
                                            <TableCell class="py-4 pl-6 font-extrabold text-slate-950 uppercase tracking-wide">
                                                {{ monthlySummary.month_name }}
                                            </TableCell>

                                            <!-- Total Tiket -->
                                            <TableCell class="text-center font-extrabold text-slate-950 py-4">
                                                {{ monthlySummary.total_tickets }}
                                            </TableCell>

                                            <!-- Total Selesai -->
                                            <TableCell class="text-center py-4">
                                                <span class="text-xs font-extrabold text-emerald-600">
                                                    {{ monthlySummary.closed }}
                                                </span>
                                            </TableCell>

                                            <!-- Total Ditolak -->
                                            <TableCell class="text-center py-4">
                                                <span class="text-xs font-extrabold text-rose-600">
                                                    {{ monthlySummary.cancelled }}
                                                </span>
                                            </TableCell>

                                            <!-- Overall Average Resolution Time -->
                                            <TableCell class="text-center text-slate-900 py-4 font-bold whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1 font-bold">
                                                    <Clock class="w-3.5 h-3.5 text-slate-600" />
                                                    {{ monthlySummary.avg_resolution_time }}
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
                                                        {{ monthlySummary.completion_rate.toFixed(2) }}%
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
