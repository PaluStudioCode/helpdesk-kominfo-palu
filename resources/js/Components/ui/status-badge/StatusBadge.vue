<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
    type?: 'ticket' | 'sla' | 'priority' | 'network' | 'infrastructure';
}>();

const variant = computed(() => {
    if (props.type === 'ticket') {
        switch (props.status) {
            case 'pending_admin': return 'pending_admin';
            case 'in_progress': return 'in_progress';
            case 'pending_approval': return 'pending_approval';
            case 'closed': return 'closed';
            case 'cancelled': return 'cancelled';
            // Legacy fallbacks
            case 'open': return 'pending_admin';
            case 'resolved': return 'pending_approval';
        }
    }
    
    if (props.type === 'sla') {
        switch (props.status) {
            case 'safe': return 'sla_safe';
            case 'warning': return 'sla_warning';
            case 'danger': return 'sla_danger';
            case 'completed': return 'sla_completed';
        }
    }
    
    if (props.type === 'priority') {
        switch (props.status) {
            case 'low': return 'priority_low';
            case 'medium': return 'priority_medium';
            case 'high': return 'priority_high';
            case 'emergency': return 'priority_emergency';
        }
    }
    
    if (props.type === 'network' || props.type === 'infrastructure') {
        switch (props.status) {
            case 'Fiber optic':
            case 'fiber_optic': return 'fiber_optic';
            case 'Perangkat/Akses':
            case 'lan': return 'perangkat_akses';
            case 'Power/poe': return 'power_poe';
            case 'Converter': return 'converter';
            case 'Layanan/jaringan':
            case 'wifi': return 'layanan_jaringan';
        }
    }
    
    return 'default';
});

const label = computed(() => {
    const labels: Record<string, string> = {
        pending_admin: 'Menunggu Verifikasi',
        in_progress: 'Sedang Dikerjakan',
        pending_approval: 'Menunggu Review Admin',
        closed: 'Selesai',
        cancelled: 'Ditolak',
        // Legacy fallbacks
        open: 'Menunggu Verifikasi',
        resolved: 'Menunggu Review Admin',
        // SLA Statuses
        safe: 'Aman',
        warning: 'Mendekati SLA',
        danger: 'Overdue SLA',
        completed: 'Selesai',
        // Priorities
        low: 'Rendah',
        medium: 'Sedang',
        high: 'Tinggi',
        emergency: 'Darurat',
        // Networks
        'Fiber optic': 'Fiber optic',
        'Perangkat/Akses': 'Perangkat/Akses',
        'Power/poe': 'Power/poe',
        'Converter': 'Converter',
        'Layanan/jaringan': 'Layanan/jaringan',
        fiber_optic: 'Fiber optic',
        lan: 'Perangkat/Akses',
        wifi: 'Layanan/jaringan',
    };
    
    return labels[props.status] || props.status;
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
