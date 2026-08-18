<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { 
    LayoutDashboard, 
    Ticket, 
    PlusCircle, 
    Building2, 
    Layers, 
    Users, 
    FileSpreadsheet, 
    UserCheck, 
    LogOut,
    Menu,
    X,
    ChevronRight,
    Bell
} from 'lucide-vue-next';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

const page = usePage();
const user = computed(() => page.props.auth.user as any);
const role = computed(() => user.value?.role);

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
            name: 'Tiket Saya',
            href: route('tickets.index'),
            icon: Ticket,
            active: route().current('tickets.*') && !route().current('tickets.create'),
            show: role.value === 'opd_user'
        },
        {
            name: 'Semua Tiket',
            href: route('tickets.index'),
            icon: Ticket,
            active: route().current('tickets.*') && !route().current('tickets.create'),
            show: role.value === 'admin' || role.value === 'technician'
        },
        {
            name: 'Buat Tiket Baru',
            href: route('tickets.create'),
            icon: PlusCircle,
            active: route().current('tickets.create'),
            show: role.value === 'opd_user' || role.value === 'admin'
        },
        {
            name: 'Master Data OPD',
            href: route('admin.departments.index'),
            icon: Building2,
            active: route().current('admin.departments.*'),
            show: role.value === 'admin'
        },
        {
            name: 'Kategori Gangguan',
            href: route('admin.categories.index'),
            icon: Layers,
            active: route().current('admin.categories.*'),
            show: role.value === 'admin'
        },
        {
            name: 'Manajemen Pengguna',
            href: route('admin.users.index'),
            icon: Users,
            active: route().current('admin.users.*'),
            show: role.value === 'admin'
        },
        {
            name: 'Laporan & Rekapitulasi',
            href: route('admin.reports.index'),
            icon: FileSpreadsheet,
            active: route().current('admin.reports.*'),
            show: role.value === 'admin'
        }
    ].filter(item => item.show);
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex flex-col md:flex-row">
        <!-- Mobile Sidebar Overlay -->
        <div 
            v-if="showingMobileMenu" 
            class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm md:hidden"
            @click="showingMobileMenu = false"
        ></div>

        <!-- Sidebar Navigation -->
        <aside 
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-900 text-slate-300 transition-all duration-300 ease-in-out md:static',
                showingMobileMenu ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
                sidebarCollapsed ? 'md:w-20' : 'w-72'
            ]"
        >
            <div class="flex h-16 items-center justify-between px-4 bg-slate-950">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-kominfo-primary text-white">
                        <Building2 class="h-5 w-5" />
                    </div>
                    <span 
                        class="text-lg font-bold text-white transition-opacity duration-300"
                        :class="sidebarCollapsed ? 'opacity-0 md:hidden' : 'opacity-100'"
                    >
                        Helpdesk
                    </span>
                </Link>
                <button 
                    @click="showingMobileMenu = false" 
                    class="md:hidden p-1 rounded-md text-slate-400 hover:text-white hover:bg-slate-800"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto py-4">
                <nav class="space-y-1 px-3">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            item.active ? 'bg-kominfo-primary text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                            'group flex items-center rounded-md px-3 py-2.5 text-sm font-medium transition-colors'
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

            <!-- Collapse Toggle Button (Desktop) -->
            <div class="hidden md:flex p-4 border-t border-slate-800">
                <button 
                    @click="sidebarCollapsed = !sidebarCollapsed" 
                    class="flex w-full items-center justify-center rounded-md p-2 text-slate-400 hover:bg-slate-800 hover:text-white transition-colors"
                >
                    <ChevronRight 
                        class="h-5 w-5 transition-transform duration-300" 
                        :class="sidebarCollapsed ? '' : 'rotate-180'" 
                    />
                </button>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8 shadow-sm z-10">
                <div class="flex items-center gap-4">
                    <button 
                        @click="showingMobileMenu = true" 
                        class="p-2 -ml-2 rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none md:hidden"
                    >
                        <Menu class="h-6 w-6" />
                    </button>
                    
                    <!-- Breadcrumbs slot / Title -->
                    <div class="hidden md:flex items-center text-sm">
                        <span class="text-slate-500 font-medium mr-2">Helpdesk Kominfo</span>
                        <ChevronRight class="h-4 w-4 text-slate-400 mr-2" />
                        <span class="text-slate-900 font-semibold" v-if="$slots.header">
                            <slot name="header" />
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Notification Bell -->
                    <button class="relative p-2 text-slate-400 hover:text-slate-600">
                        <Bell class="h-5 w-5" />
                        <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-kominfo-primary"></span>
                    </button>

                    <div class="h-6 w-px bg-slate-200"></div>

                    <!-- Profile Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" class="flex items-center gap-2 pl-2 pr-0 hover:bg-slate-50">
                                <div class="flex flex-col items-end hidden sm:flex">
                                    <span class="text-sm font-semibold text-slate-900">{{ user.name }}</span>
                                    <span class="text-xs text-slate-500 capitalize">{{ user.role.replace('_', ' ') }}</span>
                                </div>
                                <div class="h-8 w-8 rounded-full bg-kominfo-primary/10 flex items-center justify-center text-kominfo-primary border border-kominfo-primary/20">
                                    <UserCheck class="h-4 w-4" />
                                </div>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel>
                                <div class="flex flex-col space-y-1">
                                    <p class="text-sm font-medium leading-none">{{ user.name }}</p>
                                    <p class="text-xs leading-none text-muted-foreground">{{ user.email }}</p>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <Link :href="route('profile.edit')" class="w-full">
                                <DropdownMenuItem class="cursor-pointer">
                                    <UserCheck class="mr-2 h-4 w-4" />
                                    <span>Profil Pengguna</span>
                                </DropdownMenuItem>
                            </Link>
                            <DropdownMenuSeparator />
                            <Link :href="route('logout')" method="post" as="button" class="w-full">
                                <DropdownMenuItem class="text-red-600 focus:text-red-600 focus:bg-red-50 cursor-pointer">
                                    <LogOut class="mr-2 h-4 w-4" />
                                    <span>Keluar Sistem</span>
                                </DropdownMenuItem>
                            </Link>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <!-- Mobile Header Title (visible only on small screens) -->
                <div class="md:hidden mb-6" v-if="$slots.header">
                    <h1 class="text-2xl font-bold text-slate-900"><slot name="header" /></h1>
                </div>

                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Transisi untuk memperhalus hover menu sidebar collapsed */
.group:hover span {
    transition-delay: 100ms;
}
</style>
