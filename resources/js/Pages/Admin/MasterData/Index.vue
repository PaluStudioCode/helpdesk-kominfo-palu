<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DepartmentTab from './Partials/DepartmentTab.vue';
import CategoryTab from './Partials/CategoryTab.vue';
import UserTab from './Partials/UserTab.vue';
import { Building2, Layers, Users } from 'lucide-vue-next';

const props = defineProps<{
    activeTab: string;
    departments: any;
    categories: any;
    users: any;
    counts: {
        departments: number;
        categories: number;
        users: number;
    };
    allDepartments: any[];
    filters: any;
}>();

const currentTab = ref(props.activeTab || 'departments');

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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-200 pb-5">
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Pusat Kelola Master Data</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        Kelola data referensi Organisasi Perangkat Daerah (OPD), klasifikasi kendala & target SLA, serta akun pengguna sistem.
                    </p>
                </div>
            </div>

            <!-- Top Underline Navigation Tabs (Opsi 1) -->
            <div>
                <nav class="flex space-x-8 border-b border-slate-200" aria-label="Tabs">
                    <!-- Tab: Departments -->
                    <button
                        type="button"
                        @click="handleTabChange('departments')"
                        :class="[
                            currentTab === 'departments'
                                ? 'border-kominfo-primary text-kominfo-primary'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'group inline-flex items-center py-3.5 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none'
                        ]"
                    >
                        <Building2 
                            :class="[
                                currentTab === 'departments' ? 'text-kominfo-primary' : 'text-slate-400 group-hover:text-slate-500',
                                '-ml-0.5 mr-2.5 h-5 w-5'
                            ]" 
                        />
                        <span>Data OPD / Instansi</span>
                        <span
                            :class="[
                                currentTab === 'departments' 
                                    ? 'bg-blue-50 text-kominfo-primary font-bold' 
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200',
                                'ml-2.5 py-0.5 px-2 rounded-full text-xs transition-colors'
                            ]"
                        >
                            {{ counts.departments }}
                        </span>
                    </button>

                    <!-- Tab: Categories -->
                    <button
                        type="button"
                        @click="handleTabChange('categories')"
                        :class="[
                            currentTab === 'categories'
                                ? 'border-kominfo-primary text-kominfo-primary'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'group inline-flex items-center py-3.5 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none'
                        ]"
                    >
                        <Layers 
                            :class="[
                                currentTab === 'categories' ? 'text-kominfo-primary' : 'text-slate-400 group-hover:text-slate-500',
                                '-ml-0.5 mr-2.5 h-5 w-5'
                            ]" 
                        />
                        <span>Kategori Gangguan & SLA</span>
                        <span
                            :class="[
                                currentTab === 'categories' 
                                    ? 'bg-blue-50 text-kominfo-primary font-bold' 
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200',
                                'ml-2.5 py-0.5 px-2 rounded-full text-xs transition-colors'
                            ]"
                        >
                            {{ counts.categories }}
                        </span>
                    </button>

                    <!-- Tab: Users -->
                    <button
                        type="button"
                        @click="handleTabChange('users')"
                        :class="[
                            currentTab === 'users'
                                ? 'border-kominfo-primary text-kominfo-primary'
                                : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300',
                            'group inline-flex items-center py-3.5 px-1 border-b-2 font-medium text-sm transition-all focus:outline-none'
                        ]"
                    >
                        <Users 
                            :class="[
                                currentTab === 'users' ? 'text-kominfo-primary' : 'text-slate-400 group-hover:text-slate-500',
                                '-ml-0.5 mr-2.5 h-5 w-5'
                            ]" 
                        />
                        <span>Manajemen Pengguna</span>
                        <span
                            :class="[
                                currentTab === 'users' 
                                    ? 'bg-blue-50 text-kominfo-primary font-bold' 
                                    : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200',
                                'ml-2.5 py-0.5 px-2 rounded-full text-xs transition-colors'
                            ]"
                        >
                            {{ counts.users }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- Tab Content View -->
            <div class="pt-2">
                <DepartmentTab v-if="currentTab === 'departments'" :departments="departments" :filters="filters" />
                <CategoryTab v-else-if="currentTab === 'categories'" :categories="categories" :filters="filters" />
                <UserTab v-else-if="currentTab === 'users'" :users="users" :departments="allDepartments" :filters="filters" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

