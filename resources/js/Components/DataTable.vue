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
        <div class="flex items-center justify-between">
            <div class="flex flex-1 items-center space-x-2">
                <div class="relative w-full max-w-sm">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-slate-500" />
                    <Input 
                        v-model="searchQuery"
                        type="text" 
                        :placeholder="searchPlaceholder || 'Cari...'" 
                        class="pl-9"
                    />
                </div>
                <slot name="filters" />
            </div>
            <div class="flex items-center space-x-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-white">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead 
                            v-for="col in columns" 
                            :key="col.key"
                            :class="{ 'cursor-pointer select-none hover:bg-slate-50': col.sortable }"
                            @click="col.sortable && emit('sort', col.key)"
                        >
                            {{ col.label }}
                        </TableHead>
                        <TableHead v-if="$slots.actions">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="!data.data || data.data.length === 0">
                        <TableCell :colspan="columns.length + ($slots.actions ? 1 : 0)" class="h-24 text-center">
                            Tidak ada data yang ditemukan.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="item in data.data" :key="item.id">
                        <TableCell v-for="col in columns" :key="col.key">
                            <slot :name="'cell-'+col.key" :item="item">
                                {{ item[col.key] }}
                            </slot>
                        </TableCell>
                        <TableCell v-if="$slots.actions">
                            <slot name="actions-cell" :item="item" />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-2" v-if="data.last_page > 1">
            <div class="text-sm text-slate-500">
                Menampilkan {{ (data.current_page - 1) * data.per_page + 1 }} sampai 
                {{ Math.min(data.current_page * data.per_page, data.total) }} dari 
                {{ data.total }} data
            </div>
            <div class="flex items-center space-x-2">
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === 1"
                    @click="emit('page', 1)"
                >
                    <ChevronsLeft class="h-4 w-4" />
                </Button>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === 1"
                    @click="emit('page', data.current_page - 1)"
                >
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <div class="text-sm font-medium px-2">
                    Hal {{ data.current_page }} dari {{ data.last_page }}
                </div>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === data.last_page"
                    @click="emit('page', data.current_page + 1)"
                >
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button 
                    variant="outline" 
                    size="icon" 
                    class="h-8 w-8" 
                    :disabled="data.current_page === data.last_page"
                    @click="emit('page', data.last_page)"
                >
                    <ChevronsRight class="h-4 w-4" />
                </Button>
            </div>
        </div>
    </div>
</template>