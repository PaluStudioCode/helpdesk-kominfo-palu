import type { Ticket, TicketStatus, TicketPriority, InfrastructureType, NetworkType, UserRole } from '@/types';

/**
 * Format tanggal dan waktu standar Indonesia (WITA / Asia/Makassar).
 */
export const formatDateTime = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/**
 * Format tanggal dan waktu dengan suffix WITA.
 */
export const formatDateWithWita = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('id-ID', {
        timeZone: 'Asia/Makassar',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }) + ' WITA';
};

/**
 * Label teks status tiket.
 */
export const getStatusLabel = (status: TicketStatus | string): string => {
    const labels: Record<string, string> = {
        pending_admin: 'Menunggu Verifikasi',
        in_progress: 'Sedang Dikerjakan',
        on_hold: 'Tertunda (Kendala)',
        pending_approval: 'Menunggu Review',
        closed: 'Selesai',
        cancelled: 'Dibatalkan',
    };
    return labels[status] || status || '-';
};

/**
 * Warna teks status tiket (clean formal typography).
 */
export const getStatusColor = (status: TicketStatus | string): string => {
    const colors: Record<string, string> = {
        pending_admin: 'text-blue-600 font-semibold',
        in_progress: 'text-blue-600 font-semibold',
        on_hold: 'text-amber-700 font-semibold',
        pending_approval: 'text-purple-600 font-semibold',
        closed: 'text-emerald-600 font-semibold',
        cancelled: 'text-rose-600 font-semibold',
    };
    return colors[status] || 'text-slate-600 font-semibold';
};

/**
 * Label tingkat prioritas tiket.
 */
export const getPriorityLabel = (priority: TicketPriority | string | null | undefined): string => {
    if (!priority) return '-';
    const labels: Record<string, string> = {
        low: 'Rendah',
        medium: 'Sedang',
        high: 'Tinggi',
        emergency: 'Darurat',
    };
    return labels[priority] || priority;
};

/**
 * Warna teks tingkat prioritas.
 */
export const getPriorityColor = (priority: TicketPriority | string | null | undefined): string => {
    if (!priority) return 'text-slate-600';
    const colors: Record<string, string> = {
        emergency: 'text-rose-600 font-bold',
        high: 'text-amber-600 font-semibold',
        medium: 'text-blue-600 font-medium',
        low: 'text-slate-500 font-normal',
    };
    return colors[priority] || 'text-slate-600';
};

/**
 * Label jenis infrastruktur.
 */
export const getInfrastructureLabel = (infra: InfrastructureType | NetworkType | string | null | undefined): string => {
    if (!infra) return '-';
    const labels: Record<string, string> = {
        'Fiber optic': 'Fiber optic',
        'Perangkat/Akses': 'Perangkat/Akses',
        'Power/poe': 'Power/poe',
        'Converter': 'Converter',
        'Layanan/jaringan': 'Layanan/jaringan',
        fiber_optic: 'Fiber optic',
        lan: 'Perangkat/Akses',
        wifi: 'Layanan/jaringan',
    };
    return labels[infra] || infra;
};

export const getNetworkLabel = getInfrastructureLabel;

/**
 * Warna teks jenis infrastruktur.
 */
export const getInfrastructureColor = (infra: InfrastructureType | NetworkType | string | null | undefined): string => {
    if (!infra) return 'text-slate-400';
    const colors: Record<string, string> = {
        'Fiber optic': 'text-sky-700 font-semibold',
        'Perangkat/Akses': 'text-indigo-700 font-semibold',
        'Power/poe': 'text-amber-700 font-semibold',
        'Converter': 'text-pink-700 font-semibold',
        'Layanan/jaringan': 'text-emerald-700 font-semibold',
        fiber_optic: 'text-sky-700 font-semibold',
        lan: 'text-indigo-700 font-semibold',
        wifi: 'text-emerald-700 font-semibold',
    };
    return colors[infra] || 'text-slate-700 font-semibold';
};

export const getNetworkColor = getInfrastructureColor;

/**
 * Label peran pengguna (Role).
 */
export const getRoleLabel = (role: UserRole | string | null | undefined): string => {
    switch (role) {
        case 'admin': return 'Administrator';
        case 'technician': return 'Teknisi';
        case 'opd_user': return 'Pelapor OPD';
        default: return role || 'User';
    }
};

/**
 * Warna teks peran pengguna.
 */
export const getRoleColor = (role: UserRole | string | null | undefined): string => {
    switch (role) {
        case 'admin': return 'text-purple-600 font-semibold';
        case 'technician': return 'text-amber-600 font-semibold';
        case 'opd_user': return 'text-blue-600 font-semibold';
        default: return 'text-slate-600 font-semibold';
    }
};

/**
 * Hitung durasi penanganan tiket (contoh: 2h 4j 15m).
 */
export const getHandlingDuration = (ticket: Partial<Ticket>): string => {
    if (!ticket.created_at || ticket.status === 'cancelled') return '-';

    const start = new Date(ticket.created_at).getTime();
    let end: number;

    if (ticket.status && ['resolved', 'closed'].includes(ticket.status)) {
        if (ticket.resolved_at) {
            end = new Date(ticket.resolved_at).getTime();
        } else if (ticket.closed_at) {
            end = new Date(ticket.closed_at).getTime();
        } else {
            return '-';
        }
    } else {
        end = new Date().getTime();
    }

    const diffMinutes = Math.max(0, Math.floor((end - start) / (1000 * 60)));
    const days = Math.floor(diffMinutes / (60 * 24));
    const hours = Math.floor((diffMinutes % (60 * 24)) / 60);
    const minutes = diffMinutes % 60;

    const parts = [];
    if (days > 0) parts.push(`${days}h`);
    if (hours > 0) parts.push(`${hours}j`);
    if (minutes > 0 || parts.length === 0) parts.push(`${minutes}m`);

    const formattedDuration = parts.join(' ');

    if (ticket.status && !['resolved', 'closed'].includes(ticket.status)) {
        return `${formattedDuration} (berjalan)`;
    }

    return formattedDuration;
};

/**
 * Status kepatuhan SLA tiket.
 */
export const getSlaStatus = (ticket: Partial<Ticket>): { label: string; color: string; status: string } => {
    if (ticket.status === 'cancelled') {
        return { status: 'cancelled', label: 'Dibatalkan', color: 'text-slate-400' };
    }

    if (ticket.status === 'pending_admin') {
        return { status: 'pending_admin', label: '⏱ SLA ditangguhkan (Menunggu Verifikasi)', color: 'text-blue-700 font-medium' };
    }

    if (ticket.status === 'on_hold') {
        return { status: 'on_hold', label: '⏱ SLA dijeda sementara (Clock Paused)', color: 'text-amber-700 font-semibold' };
    }

    if (!ticket.due_at) {
        return { status: 'none', label: '-', color: 'text-slate-400' };
    }

    const dueAt = new Date(ticket.due_at).getTime();
    const completionTime = ticket.resolved_at 
        ? new Date(ticket.resolved_at).getTime() 
        : (ticket.closed_at ? new Date(ticket.closed_at).getTime() : null);

    if (ticket.status && ['resolved', 'closed'].includes(ticket.status) && completionTime) {
        if (completionTime <= dueAt) {
            return { status: 'on_time', label: 'Tepat Waktu', color: 'text-emerald-600 font-semibold' };
        } else {
            return { status: 'late', label: 'Terlambat', color: 'text-rose-600 font-semibold' };
        }
    }

    const now = new Date().getTime();
    const diffHours = (dueAt - now) / (1000 * 60 * 60);

    if (diffHours < 0) {
        return { status: 'overdue', label: 'Overdue SLA', color: 'text-rose-600 font-bold' };
    } else if (diffHours <= 2) {
        return { status: 'approaching', label: 'Mendekati Batas', color: 'text-amber-600 font-semibold' };
    } else {
        return { status: 'on_track', label: 'Dalam Target', color: 'text-emerald-600 font-medium' };
    }
};

/**
 * Helper untuk mengekstrak informasi revisi aktif (jika tiket dalam status in_progress setelah diminta revisi).
 */
export const getRevisionInfo = (ticket: any): { adminName: string; instruction: string; requestedAt: string } | null => {
    if (!ticket || ticket.status !== 'in_progress' || !ticket.status_histories || !Array.isArray(ticket.status_histories)) {
        return null;
    }

    const revisionHistories = ticket.status_histories.filter((h: any) => 
        h.previous_status === 'pending_approval' && h.new_status === 'in_progress'
    );

    if (revisionHistories.length === 0) return null;

    const latestRevision = revisionHistories.reduce((prev: any, curr: any) => {
        return (new Date(curr.created_at).getTime() > new Date(prev.created_at).getTime()) ? curr : prev;
    }, revisionHistories[0]);

    // Pastikan tidak ada transisi status pending_approval atau closed setelah riwayat revisi ini
    const newerTransitions = ticket.status_histories.some((h: any) => 
        new Date(h.created_at).getTime() > new Date(latestRevision.created_at).getTime() &&
        ['pending_approval', 'closed'].includes(h.new_status)
    );
    if (newerTransitions) return null;

    let instruction = latestRevision.comment || '';
    if (instruction.startsWith('Admin meminta perbaikan ulang/revisi. Instruksi: ')) {
        instruction = instruction.replace('Admin meminta perbaikan ulang/revisi. Instruksi: ', '');
    }

    return {
        adminName: latestRevision.changer?.name || 'Administrator Diskominfo',
        instruction: instruction,
        requestedAt: latestRevision.created_at,
    };
};

