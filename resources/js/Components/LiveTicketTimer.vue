<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
    Clock, 
    Timer, 
    AlertTriangle, 
    CheckCircle2, 
    PauseCircle, 
    Hourglass
} from 'lucide-vue-next';
import { formatDateWithWita } from '@/lib/ticket-helpers';

interface Props {
    ticket: {
        id: number;
        status: string;
        priority?: string | null;
        created_at: string;
        assigned_at?: string | null;
        due_at?: string | null;
        hold_started_at?: string | null;
        total_hold_duration_minutes?: number | null;
        resolved_at?: string | null;
        closed_at?: string | null;
        cancelled_at?: string | null;
    };
    variant?: 'badge' | 'card' | 'inline';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'card'
});

// Real-time ticking timestamp (updates every 1000ms)
const now = ref(Date.now());
let timerInterval: any = null;

onMounted(() => {
    timerInterval = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
});

// Helper 2-digit padding
const pad = (num: number): string => Math.floor(Math.max(0, num)).toString().padStart(2, '0');

// Format milliseconds into digital time (HH:MM:SS or DDd HH:MM:SS)
const formatDigital = (ms: number): { days: number; hours: string; minutes: string; seconds: string; formatted: string } => {
    const totalSeconds = Math.floor(Math.max(0, ms) / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const formatted = days > 0 
        ? `${days}h ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`
        : `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;

    return {
        days,
        hours: pad(hours),
        minutes: pad(minutes),
        seconds: pad(seconds),
        formatted
    };
};

// Target SLA Computation
const slaData = computed(() => {
    if (props.ticket.status === 'cancelled') {
        return {
            type: 'cancelled',
            label: 'Tiket Dibatalkan',
            subLabel: 'Perhitungan SLA tidak aktif',
            color: 'text-slate-500',
            bg: 'bg-slate-50',
            border: 'border-slate-200'
        };
    }

    if (props.ticket.status === 'pending_admin') {
        return {
            type: 'pending',
            label: 'SLA Belum Berjalan',
            subLabel: 'Dimulai setelah verifikasi admin',
            color: 'text-blue-700',
            bg: 'bg-blue-50',
            border: 'border-blue-200'
        };
    }

    if (props.ticket.status === 'on_hold') {
        let frozenRemainingText = 'Dijeda';
        if (props.ticket.due_at && props.ticket.hold_started_at) {
            const dueTime = new Date(props.ticket.due_at).getTime();
            const holdTime = new Date(props.ticket.hold_started_at).getTime();
            const diff = dueTime - holdTime;
            if (diff > 0) {
                const digital = formatDigital(diff);
                frozenRemainingText = `Sisa: ${digital.formatted} (Dijeda)`;
            } else {
                frozenRemainingText = 'Melewati SLA saat dijeda';
            }
        }
        return {
            type: 'on_hold',
            label: '⏱ SLA Dijeda Sementara',
            subLabel: frozenRemainingText,
            color: 'text-amber-700',
            bg: 'bg-amber-50',
            border: 'border-amber-200'
        };
    }

    if (['resolved', 'closed'].includes(props.ticket.status)) {
        if (!props.ticket.due_at) {
            return {
                type: 'completed',
                label: '✓ Selesai',
                subLabel: 'Perbaikan telah diselesaikan',
                color: 'text-emerald-700',
                bg: 'bg-emerald-50',
                border: 'border-emerald-200'
            };
        }
        const dueTime = new Date(props.ticket.due_at).getTime();
        const endTime = props.ticket.resolved_at 
            ? new Date(props.ticket.resolved_at).getTime() 
            : (props.ticket.closed_at ? new Date(props.ticket.closed_at).getTime() : now.value);
        const achieved = endTime <= dueTime;
        return {
            type: 'completed',
            label: achieved ? '✓ SLA Tercapai' : '⚠️ SLA Terlewati',
            subLabel: achieved ? 'Selesai tepat waktu' : 'Selesai melebihi target waktu',
            color: achieved ? 'text-emerald-700' : 'text-rose-700',
            bg: achieved ? 'bg-emerald-50' : 'bg-rose-50',
            border: achieved ? 'border-emerald-200' : 'border-rose-200'
        };
    }

    if (!props.ticket.due_at) {
        return {
            type: 'none',
            label: 'Target SLA Belum Ditentukan',
            subLabel: '-',
            color: 'text-slate-500',
            bg: 'bg-slate-50',
            border: 'border-slate-200'
        };
    }

    // Active in-progress SLA countdown
    const dueTime = new Date(props.ticket.due_at).getTime();
    const diffMs = dueTime - now.value;
    const isOverdue = diffMs <= 0;
    const absDiffMs = Math.abs(diffMs);
    const digital = formatDigital(absDiffMs);
    const isApproaching = !isOverdue && diffMs <= 2 * 3600 * 1000; // <= 2 jam

    if (isOverdue) {
        return {
            type: 'overdue',
            isOverdue: true,
            digital: `+${digital.formatted}`,
            label: 'Melewati Batas SLA',
            subLabel: `Terlambat ${digital.formatted}`,
            color: 'text-rose-600',
            bg: 'bg-rose-50',
            border: 'border-rose-200',
            badgeBg: 'bg-rose-600 text-white'
        };
    }

    if (isApproaching) {
        return {
            type: 'approaching',
            isOverdue: false,
            digital: digital.formatted,
            label: 'Mendekati Batas SLA',
            subLabel: `Sisa waktu ${digital.formatted}`,
            color: 'text-amber-700',
            bg: 'bg-amber-50',
            border: 'border-amber-300',
            badgeBg: 'bg-amber-500 text-white'
        };
    }

    return {
        type: 'safe',
        isOverdue: false,
        digital: digital.formatted,
        label: 'Sisa Waktu Target SLA',
        subLabel: `${digital.formatted} tersisa`,
        color: 'text-emerald-700',
        bg: 'bg-emerald-50',
        border: 'border-emerald-200',
        badgeBg: 'bg-emerald-600 text-white'
    };
});
</script>

<template>
    <!-- 1. BADGE VARIANT (Compact chip for header/action bar) -->
    <div v-if="variant === 'badge'" class="inline-flex items-center">
        <template v-if="slaData.type === 'safe'">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <Timer class="w-3.5 h-3.5 text-emerald-600" />
                <span>Sisa SLA: {{ slaData.digital }}</span>
            </span>
        </template>
        <template v-else-if="slaData.type === 'approaching'">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-amber-100 text-amber-800 border border-amber-300">
                <Hourglass class="w-3.5 h-3.5 text-amber-600 animate-spin" />
                <span>Sisa SLA: {{ slaData.digital }}</span>
            </span>
        </template>
        <template v-else-if="slaData.type === 'overdue'">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-rose-100 text-rose-700 border border-rose-200 animate-pulse">
                <AlertTriangle class="w-3.5 h-3.5 text-rose-600" />
                <span>Overdue SLA: {{ slaData.digital }}</span>
            </span>
        </template>
        <template v-else-if="slaData.type === 'on_hold'">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">
                <PauseCircle class="w-3.5 h-3.5 text-amber-600" />
                <span>SLA Dijeda</span>
            </span>
        </template>
    </div>

    <!-- 2. INLINE VARIANT (For Table Grid / Section 2 Target SLA cell) -->
    <div v-else-if="variant === 'inline'" class="space-y-1">
        <template v-if="slaData.type === 'safe' || slaData.type === 'approaching' || slaData.type === 'overdue'">
            <div class="flex items-center gap-2">
                <div 
                    class="font-mono text-base font-extrabold tracking-tight tabular-nums flex items-center gap-1.5"
                    :class="slaData.color"
                >
                    <Timer v-if="slaData.type === 'safe'" class="w-4 h-4 text-emerald-600" />
                    <Hourglass v-else-if="slaData.type === 'approaching'" class="w-4 h-4 text-amber-600 animate-spin" />
                    <AlertTriangle v-else class="w-4 h-4 text-rose-600 animate-pulse" />
                    <span>{{ slaData.digital }}</span>
                </div>
            </div>
            <p class="text-[11px] font-medium" :class="slaData.color">
                {{ slaData.label }}
            </p>
        </template>
        <template v-else>
            <p class="text-xs font-medium" :class="slaData.color">
                {{ slaData.label }}
            </p>
            <p class="text-[11px] text-slate-500">
                {{ slaData.subLabel }}
            </p>
        </template>
    </div>

    <!-- 3. CARD VARIANT (Focused SLA Countdown HUD) -->
    <div 
        v-else 
        class="p-4 rounded-xl border transition-all duration-300"
        :class="[slaData.bg, slaData.border]"
    >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span 
                        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider"
                        :class="[
                            slaData.type === 'overdue' ? 'bg-rose-200 text-rose-800' :
                            (slaData.type === 'approaching' ? 'bg-amber-200 text-amber-900' :
                            (slaData.type === 'safe' ? 'bg-emerald-200 text-emerald-900' :
                            (slaData.type === 'on_hold' ? 'bg-amber-200 text-amber-900' : 'bg-slate-200 text-slate-800')))
                        ]"
                    >
                        <span 
                            class="w-2 h-2 rounded-full"
                            :class="[
                                slaData.type === 'overdue' ? 'bg-rose-600 animate-ping' :
                                (slaData.type === 'approaching' ? 'bg-amber-600 animate-pulse' :
                                (slaData.type === 'safe' ? 'bg-emerald-600 animate-pulse' :
                                (slaData.type === 'on_hold' ? 'bg-amber-600' : 'bg-slate-500')))
                            ]"
                        />
                        {{ slaData.label }}
                    </span>
                    <span v-if="ticket.due_at" class="text-xs text-slate-500">
                        Target: <strong class="text-slate-700">{{ formatDateWithWita(ticket.due_at) }}</strong>
                    </span>
                </div>

                <div v-if="slaData.digital" class="flex items-baseline gap-2 pt-1">
                    <div 
                        class="font-mono text-3xl sm:text-4xl font-black tracking-tight tabular-nums"
                        :class="slaData.color"
                    >
                        {{ slaData.digital }}
                    </div>
                    <span class="text-xs font-semibold" :class="slaData.color">
                        {{ slaData.type === 'overdue' ? 'Terlambat (Overdue)' : 'Sisa Waktu SLA' }}
                    </span>
                </div>
                <div v-else class="text-xs text-slate-600 pt-1">
                    {{ slaData.subLabel }}
                </div>
            </div>

            <div v-if="ticket.due_at && (slaData.type === 'safe' || slaData.type === 'approaching' || slaData.type === 'overdue')" class="text-right hidden sm:block">
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status SLA</p>
                <p class="text-sm font-bold" :class="slaData.color">
                    {{ slaData.type === 'overdue' ? 'Melewati Batas Target' : (slaData.type === 'approaching' ? 'Perlu Segera Ditangani' : 'Berjalan Sesuai Target') }}
                </p>
            </div>
        </div>
    </div>
</template>
