<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/components/ui/status-badge/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Plus, Eye } from 'lucide-vue-next';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

const props = defineProps<{
    tickets: any;
    filters: any;
    canCreateOnBehalf: boolean;
}>();

const searchQuery = ref(props.filters?.search || '');
const currentTab = ref(props.filters?.status || 'all');

const handleSearch = (value: string) => {
    router.get(route('tickets.index'), {
        ...props.filters,
        search: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};

const handleSort = (key: string) => {
    const currentSort = props.filters?.sort;
    const currentDir = props.filters?.direction;
    const newDir = currentSort === key && currentDir === 'asc' ? 'desc' : 'asc';
    
    router.get(route('tickets.index'), {
        ...props.filters,
        sort: key,
        direction: newDir
    }, { preserveState: true });
};

const handlePage = (page: number) => {
    router.get(route('tickets.index'), {
        ...props.filters,
        page
    }, { preserveState: true });
};

const changeTab = (value: string) => {
    currentTab.value = value;
    router.get(route('tickets.index'), {
        ...props.filters,
        status: value,
        page: 1
    }, { preserveState: true, preserveScroll: true });
};

const columns = [
    { key: 'ticket_number', label: 'No. Tiket', sortable: true },
    { key: 'title', label: 'Subjek', sortable: false },
    { key: 'department', label: 'OPD / Instansi', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'sla_status', label: 'SLA', sortable: false },
];

const getSlaStatus = (ticket: any) => {
    if (!ticket.due_at || ['resolved', 'closed', 'cancelled'].includes(ticket.status)) return null;
    
    const now = new Date();
    const dueAt = new Date(ticket.due_at);
    const diffHours = (dueAt.getTime() - now.getTime()) / (1000 * 60 * 60);

    if (diffHours < 0) return { status: 'danger', label: 'Overdue' };
    if (diffHours <= 2) return { status: 'warning', label: 'Mendekati SLA' };
    return { status: 'safe', label: 'Aman' };
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric'
    });
};
</script>

<template>
    <Head title="Antrean Tiket" />

    <AuthenticatedLayout>
        <template #header>
            Antrean Tiket Gangguan
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash.success" class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-md p-4 mb-4">
                {{ $page.props.flash.success }}
            </div>

            <!-- Quick Filter Tabs -->
            <Tabs :modelValue="currentTab" @update:modelValue="changeTab" class="w-full overflow-x-auto">
                <TabsList class="inline-flex h-10 items-center justify-center rounded-md bg-slate-100 p-1 text-slate-500">
                    <TabsTrigger value="all" class="px-3 py-1.5">Semua</TabsTrigger>
                    <TabsTrigger value="open" class="px-3 py-1.5 text-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-800">Open</TabsTrigger>
                    <TabsTrigger value="in_progress" class="px-3 py-1.5 text-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-800">In Progress</TabsTrigger>
                    <TabsTrigger value="resolved" class="px-3 py-1.5 text-emerald-600 data-[state=active]:bg-emerald-100 data-[state=active]:text-emerald-800">Resolved</TabsTrigger>
                    <!-- SLA Filters mapped to query conditions in controller -->
                    <TabsTrigger value="mendekati_sla" class="px-3 py-1.5 text-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-800">Mendekati SLA</TabsTrigger>
                    <TabsTrigger value="overdue" class="px-3 py-1.5 text-red-600 data-[state=active]:bg-red-100 data-[state=active]:text-red-800">Overdue SLA</TabsTrigger>
                </TabsList>
            </Tabs>

            <DataTable 
                :columns="columns" 
                :data="tickets"
                :modelValue="searchQuery"
                @update:modelValue="handleSearch"
                @sort="handleSort"
                @page="handlePage"
                searchPlaceholder="Cari no tiket atau judul..."
            >
                <template #actions>
                    <Link :href="route('tickets.create')">
                        <Button class="bg-kominfo-primary hover:bg-kominfo-primary-dark">
                            <Plus class="w-4 h-4 mr-2" /> {{ canCreateOnBehalf ? 'Buat Tiket (On-Behalf)' : 'Buat Tiket Baru' }}
                        </Button>
                    </Link>
                </template>

                <template #cell-ticket_number="{ item }">
                    <div class="font-medium text-slate-900">{{ item.ticket_number }}</div>
                    <div class="text-xs text-slate-500">{{ formatDate(item.created_at) }}</div>
                </template>

                <template #cell-title="{ item }">
                    <div class="font-medium text-slate-900 truncate max-w-xs" :title="item.title">{{ item.title }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <StatusBadge type="network" :status="item.network_type" />
                        <StatusBadge type="priority" :status="item.priority" />
                    </div>
                </template>

                <template #cell-department="{ item }">
                    <span class="text-sm">{{ item.department.name }}</span>
                </template>

                <template #cell-status="{ item }">
                    <StatusBadge type="ticket" :status="item.status" />
                </template>

                <template #cell-sla_status="{ item }">
                    <StatusBadge v-if="getSlaStatus(item)" type="sla" :status="getSlaStatus(item).status" />
                    <span v-else class="text-xs text-slate-400">-</span>
                </template>

                <template #actions-cell="{ item }">
                    <Link :href="route('tickets.show', item.id)">
                        <Button variant="ghost" size="sm" class="h-8 border border-slate-200">
                            <Eye class="w-4 h-4 mr-2" /> Detail
                        </Button>
                    </Link>
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>