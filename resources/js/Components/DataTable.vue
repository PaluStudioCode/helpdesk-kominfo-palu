<script setup lang="ts">
import { ref, watch } from 'vue';
import { 
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';

const props = defineProps<{
    columns: Array<{ key: string, label: string, sortable?: boolean }>;
    data: {
        data: Array<any>;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<any>;
    };
    searchPlaceholder?: string;
    modelValue?: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'sort', key: string): void;
    (e: 'page', page: number): void;
}>();

const searchQuery = ref(props.modelValue || '');
let searchTimeout: any = null;

watch(searchQuery, (val) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        emit('update:modelValue', val);
    }, 300);
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-2">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-500" />
                    <Input 
                        v-model="searchQuery"
                        type="text" 
                        :placeholder="searchPlaceholder || 'Cari...'" 
                        class="pl-9 bg-white w-full"
                    />
                </div>
                <div class="flex items-center gap-2 overflow-x-auto pb-1 sm:pb-0">
                    <slot name="filters" />
                </div>
            </div>
            <div class="flex items-center justify-start sm:justify-end gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="overflow-x-auto w-full">
                <Table class="min-w-[650px] sm:min-w-full">
                    <TableHeader>
                        <TableRow class="bg-slate-50/80">
                            <TableHead 
                                v-for="col in columns" 
                                :key="col.key"
                                :class="{ 'cursor-pointer select-none hover:bg-slate-100': col.sortable }"
                                @click="col.sortable && emit('sort', col.key)"
                            >
                                {{ col.label }}
                            </TableHead>
                            <TableHead v-if="$slots.actions || $slots['actions-cell']" class="text-right pr-4">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="!data.data || data.data.length === 0">
                            <TableCell :colspan="columns.length + ($slots.actions || $slots['actions-cell'] ? 1 : 0)" class="h-24 text-center text-slate-500">
                                Tidak ada data yang ditemukan.
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="item in data.data" :key="item.id" class="hover:bg-slate-50/50">
                            <TableCell v-for="col in columns" :key="col.key">
                                <slot :name="'cell-'+col.key" :item="item">
                                    {{ item[col.key] }}
                                </slot>
                            </TableCell>
                            <TableCell v-if="$slots.actions || $slots['actions-cell']" class="text-right pr-4">
                                <slot name="actions-cell" :item="item" />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-1 py-1" v-if="data.last_page > 1">
            <div class="text-xs text-slate-500 text-center sm:text-left">
                Menampilkan {{ (data.current_page - 1) * data.per_page + 1 }} sampai 
                {{ Math.min(data.current_page * data.per_page, data.total) }} dari 
                {{ data.total }} data
            </div>
            <div class="flex items-center space-x-1.5 sm:space-x-2">
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === 1"
                    @click="emit('page', 1)"
                    title="Halaman Pertama"
                >
                    <ChevronsLeft class="h-4 w-4" />
                </Button>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === 1"
                    @click="emit('page', data.current_page - 1)"
                    title="Halaman Sebelumnya"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="text-xs font-medium px-2 text-slate-700">
                    Hal {{ data.current_page }} dari {{ data.last_page }}
                </div>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === data.last_page"
                    @click="emit('page', data.current_page + 1)"
                    title="Halaman Berikutnya"
                >
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === data.last_page"
                    @click="emit('page', data.last_page)"
                    title="Halaman Terakhir"
                >
                    <ChevronsRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>