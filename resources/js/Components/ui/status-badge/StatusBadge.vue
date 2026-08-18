<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    status: string;
    type?: 'ticket' | 'sla' | 'priority' | 'network';
}>();

const variant = computed(() => {
    if (props.type === 'ticket') {
        switch (props.status) {
            case 'open': return 'open';
            case 'in_progress': return 'in_progress';
            case 'resolved': return 'resolved';
            case 'closed': return 'closed';
            case 'cancelled': return 'cancelled';
        }
    }
    
    if (props.type === 'sla') {
        switch (props.status) {
            case 'safe': return 'sla_safe';
            case 'warning': return 'sla_warning';
            case 'danger': return 'sla_danger';
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
    
    if (props.type === 'network') {
        switch (props.status) {
            case 'fiber_optic': return 'fiber_optic';
            case 'lan': return 'lan';
            case 'wifi': return 'wifi';
        }
    }
    
    return 'default';
});

const label = computed(() => {
    const labels: Record<string, string> = {
        open: 'Open',
        in_progress: 'In Progress',
        resolved: 'Resolved',
        closed: 'Closed',
        cancelled: 'Cancelled',
        safe: 'Aman',
        warning: 'Mendekati SLA',
        danger: 'Overdue SLA',
        low: 'Low',
        medium: 'Medium',
        high: 'High',
        emergency: 'Emergency',
        fiber_optic: 'Fiber Optic',
        lan: 'LAN',
        wifi: 'WiFi',
    };
    
    return labels[props.status] || props.status;
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
