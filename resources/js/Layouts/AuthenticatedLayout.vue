<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Toaster, useToast } from '@/Components/ui/toast';
import { 
    LayoutDashboard, 
    Ticket, 
    Building2, 
    FileSpreadsheet, 
    UserCheck, 
    LogOut,
    Menu,
    X,
    ChevronRight,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user as any);
const role = computed(() => user.value?.role);
const { toast } = useToast();

// Global Toast Listener for Laravel Session Flash
watch(
    () => page.props.flash,
    (flash: any) => {
        if (!flash) return;

        if (flash.success) {
            toast({
                title: 'Berhasil',
                description: flash.success,
                variant: 'success',
            });
        }

        if (flash.error) {
            toast({
                title: 'Perhatian / Terjadi Kesalahan',
                description: flash.error,
                variant: 'destructive',
            });
        }
    },
    { immediate: true, deep: true }
);

const showingMobileMenu = ref(false);
const sidebarCollapsed = ref(false);

const navigation = computed(() => {
    return [
        {
            name: 'Dashboard',
            href: route('dashboard'),
            icon: LayoutDashboard,
            active: route().current('dashboard'),
            show: true
        },
        {
            name: role.value === 'opd_user' ? 'Tiket Saya' : 'Tiket Gangguan',
            href: route('tickets.index'),
            icon: Ticket,
            active: route().current('tickets.*'),
            show: true
        },
        {
            name: 'Master Data',
            href: route('admin.master-data.index'),
            icon: Building2,
            active: route().current('admin.master-data.*') || route().current('admin.departments.*') || route().current('admin.categories.*') || route().current('admin.users.*'),
            show: role.value === 'admin'
        },
        {
            name: 'Laporan & Rekap',
            href: route('admin.reports.index'),
            icon: FileSpreadsheet,
            active: route().current('admin.reports.*'),
            show: role.value === 'admin'
        }
    ].filter(item => item.show);
});
</script>

<template>
    <div class="h-screen bg-slate-50 flex flex-col md:flex-row overflow-hidden">
        <!-- Mobile Sidebar Overlay -->
        <div 
            v-if="showingMobileMenu" 
            class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm md:hidden"
            @click="showingMobileMenu = false"
        ></div>

        <!-- Mobile Top App Bar (Only visible on small mobile screens) -->
        <div class="md:hidden flex h-14 items-center justify-between px-4 bg-slate-900 text-white z-30 shrink-0">
            <div class="flex items-center gap-3">
                <button 
                    @click="showingMobileMenu = true" 
                    class="p-1.5 -ml-1 rounded-md text-slate-300 hover:text-white hover:bg-slate-800 focus:outline-none"
                >
                    <Menu class="h-6 w-6" />
                </button>
                <div class="flex items-center gap-2.5 font-bold text-base">
                    <div class="h-7 w-7 rounded-md bg-white p-0.5 flex items-center justify-center shrink-0">
                        <img src="/storage/logo-only.png" alt="Logo Kominfo" class="h-full w-full object-contain" />
                    </div>
                    <span>Helpdesk Kominfo</span>
                </div>
            </div>
            <Link :href="route('profile.edit')" class="flex items-center gap-2" title="Profil Pengguna">
                <div class="h-7 w-7 rounded-full bg-kominfo-primary/20 flex items-center justify-center text-kominfo-primary border border-kominfo-primary/30">
                    <UserCheck class="h-4 w-4" />
                </div>
            </Link>
        </div>

        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-900 text-slate-300 transition-all duration-300 ease-in-out md:static md:h-screen md:shrink-0',
                showingMobileMenu ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                sidebarCollapsed ? 'md:w-20' : 'w-64'
            ]"
        >
            <!-- Sidebar Header / Logo -->
            <div class="flex h-16 items-center justify-between px-4 bg-slate-950 border-b border-slate-800/60">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <div class="h-9 w-9 shrink-0 rounded-lg bg-white p-1 flex items-center justify-center shadow-sm">
                        <img src="/storage/logo-only.png" alt="Logo Kominfo" class="h-full w-full object-contain" />
                    </div>
                    <div 
                        class="flex flex-col transition-opacity duration-300"
                        :class="sidebarCollapsed ? 'opacity-0 md:hidden' : 'opacity-100'"
                    >
                        <span class="text-sm font-bold text-white tracking-wide leading-tight">Helpdesk</span>
                        <span class="text-[10px] text-slate-400 font-medium">Diskominfo Palu</span>
                    </div>
                </Link>
                <button 
                    @click="showingMobileMenu = false" 
                    class="md:hidden p-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-800"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Sidebar Nav Items -->
            <div class="flex-1 overflow-y-auto py-4">
                <nav class="space-y-1.5 px-3">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            item.active ? 'bg-kominfo-primary text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white',
                            'group flex items-center rounded-md px-3 py-2.5 text-sm font-medium transition-all'
                        ]"
                        :title="sidebarCollapsed ? item.name : ''"
                    >
                        <component 
                            :is="item.icon" 
                            :class="[
                                item.active ? 'text-white' : 'text-slate-400 group-hover:text-white',
                                'h-5 w-5 shrink-0',
                                sidebarCollapsed ? 'mr-0' : 'mr-3'
                            ]" 
                        />
                        <span 
                            class="transition-opacity duration-300 whitespace-nowrap"
                            :class="sidebarCollapsed ? 'opacity-0 md:hidden' : 'opacity-100'"
                        >
                            {{ item.name }}
                        </span>
                    </Link>
                </nav>
            </div>

            <!-- Sidebar Bottom Profile Section (Hybrid Compact Card - No Pop-up) -->
            <div class="p-3 border-t border-slate-800 bg-slate-950/60">
                <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg bg-slate-900/60 border border-slate-800/60">
                    <!-- User Info & Link to Profile -->
                    <Link 
                        :href="route('profile.edit')" 
                        class="flex items-center gap-2.5 min-w-0 flex-1 rounded-md p-1 hover:bg-slate-800/80 transition-colors group"
                        :title="sidebarCollapsed ? `Profil: ${user.name} (${user.role})` : 'Buka Profil Pengguna'"
                    >
                        <div class="h-8 w-8 shrink-0 rounded-full bg-kominfo-primary/20 flex items-center justify-center text-kominfo-primary border border-kominfo-primary/30 group-hover:border-kominfo-primary/60 transition-colors">
                            <UserCheck class="h-4 w-4" />
                        </div>
                        <div 
                            class="flex-1 min-w-0 transition-opacity duration-300"
                            :class="sidebarCollapsed ? 'opacity-0 md:hidden' : 'opacity-100'"
                        >
                            <p class="text-xs font-semibold text-white truncate leading-tight group-hover:text-kominfo-primary transition-colors">
                                {{ user.name }}
                            </p>
                            <p class="text-[10px] text-slate-400 capitalize truncate mt-0.5">
                                {{ user.role.replace('_', ' ') }}
                            </p>
                        </div>
                    </Link>

                    <!-- Direct Logout Button (1-Click) -->
                    <Link 
                        :href="route('logout')" 
                        method="post" 
                        as="button" 
                        class="p-1.5 rounded-md text-slate-400 hover:text-rose-400 hover:bg-rose-950/40 transition-colors shrink-0"
                        :class="sidebarCollapsed ? 'hidden' : 'block'"
                        title="Keluar Sistem"
                    >
                        <LogOut class="h-4 w-4" />
                    </Link>
                </div>

                <!-- Collapse Toggle Button (Desktop) -->
                <div class="hidden md:flex mt-2 pt-2 border-t border-slate-800/80">
                    <button 
                        @click="sidebarCollapsed = !sidebarCollapsed" 
                        class="flex w-full items-center justify-center rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white transition-colors"
                        title="Perkecil Sidebar"
                    >
                        <ChevronRight 
                            class="h-4 w-4 transition-transform duration-300" 
                            :class="sidebarCollapsed ? '' : 'rotate-180'" 
                        />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content Area (Full Screen without Top Navbar) -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 bg-slate-50">
            <div class="mx-auto max-w-7xl">
                <slot />
            </div>
        </main>

        <!-- Global Toast Notification Provider -->
        <Toaster />
    </div>
</template>

<style scoped>
/* Transisi untuk memperhalus hover menu sidebar collapsed */
.group:hover span {
    transition-delay: 100ms;
}
</style>
