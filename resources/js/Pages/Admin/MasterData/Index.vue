<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DepartmentTab from './Partials/DepartmentTab.vue';
import CategoryTab from './Partials/CategoryTab.vue';
import UserTab from './Partials/UserTab.vue';
import DeviceTab from './Partials/DeviceTab.vue';
import MaterialTab from './Partials/MaterialTab.vue';

const props = defineProps<{
    activeTab: string;
    departments: any;
    categories: any;
    users: any;
    devices: any;
    materials: any;
    counts: {
        departments: number;
        categories: number;
        users: number;
        devices: number;
        materials: number;
    };
    allDepartments: any[];
    filters: any;
}>();

const currentTab = ref(props.activeTab || 'departments');

watch(() => props.activeTab, (newTab) => {
    if (newTab) {
        currentTab.value = newTab;
    }
});

const tabs = computed(() => [
    { id: 'departments', label: 'Data OPD / Instansi', count: props.counts?.departments ?? 0 },
    { id: 'categories', label: 'Kategori Gangguan & SLA', count: props.counts?.categories ?? 0 },
    { id: 'users', label: 'Manajemen Pengguna', count: props.counts?.users ?? 0 },
    { id: 'devices', label: 'Perangkat / Node Jaringan', count: props.counts?.devices ?? 0 },
    { id: 'materials', label: 'Material / Suku Cadang', count: props.counts?.materials ?? 0 },
]);

const handleTabChange = (val: string) => {
    currentTab.value = val;
    router.get(route('admin.master-data.index'), {
        tab: val
    }, { preserveState: true, preserveScroll: true });
};
</script>

<template>
    <Head title="Master Data Hub" />

    <AuthenticatedLayout>
        <template #header>
            Master Data Hub
        </template>

        <div class="space-y-6">
            <!-- Header Title and Description -->
            <div class="border-b border-slate-200 pb-5">
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Pusat Kelola Master Data</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Kelola data referensi Organisasi Perangkat Daerah (OPD), klasifikasi kendala & target SLA, akun pengguna sistem, serta katalog perangkat dan material perbaikan.
                </p>
            </div>

            <!-- Top Navigation Tabs (Grid, Style Biasa, Tanpa Ikon & Deskripsi) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-w-3xl" role="tablist" aria-label="Tabs Master Data">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    role="tab"
                    :aria-selected="currentTab === tab.id"
                    @click="handleTabChange(tab.id)"
                    :class="[
                        currentTab === tab.id
                            ? 'bg-kominfo-primary text-white border-kominfo-primary shadow-xs font-semibold'
                            : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50 hover:border-slate-300 font-medium shadow-2xs',
                        'flex items-center justify-between px-3.5 py-2.5 rounded-lg border text-xs sm:text-sm transition-all focus:outline-none cursor-pointer'
                    ]"
                >
                    <span class="truncate">{{ tab.label }}</span>
                    <span
                        :class="[
                            currentTab === tab.id 
                                ? 'bg-white/20 text-white font-bold' 
                                : 'bg-slate-100 text-slate-600 font-medium',
                            'ml-2 py-0.5 px-2 rounded-full text-xs shrink-0 transition-colors'
                        ]"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Tab Content View -->
            <div class="pt-1">
                <DepartmentTab v-if="currentTab === 'departments'" :departments="departments" :filters="filters" />
                <CategoryTab v-else-if="currentTab === 'categories'" :categories="categories" :filters="filters" />
                <UserTab v-else-if="currentTab === 'users'" :users="users" :departments="allDepartments" :filters="filters" />
                <DeviceTab v-else-if="currentTab === 'devices'" :devices="devices" :filters="filters" />
                <MaterialTab v-else-if="currentTab === 'materials'" :materials="materials" :filters="filters" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

