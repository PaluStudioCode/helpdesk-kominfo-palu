<script setup lang="ts">
import { ref, computed } from 'vue';
import { UploadCloud, X, File as FileIcon } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: File[];
    multiple?: boolean;
    maxFiles?: number;
    maxSizeMB?: number;
    accept?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', files: File[]): void;
    (e: 'error', message: string): void;
}>();

const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const maxFilesAllowed = computed(() => props.multiple ? (props.maxFiles || 3) : 1);
const acceptTypes = computed(() => props.accept || 'image/jpeg,image/png,application/pdf');

const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    if (!props.disabled) isDragging.value = true;
};

const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
};

const validateFiles = (files: FileList | File[]): File[] => {
    const validFiles: File[] = [];
    const currentCount = props.modelValue.length;
    
    // Check max files limit
    if (currentCount + files.length > maxFilesAllowed.value) {
        emit('error', `Maksimal hanya diperbolehkan unggah ${maxFilesAllowed.value} berkas.`);
        return validFiles;
    }

    const maxSizeBytes = (props.maxSizeMB || 5) * 1024 * 1024;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        
        if (file.size > maxSizeBytes) {
            emit('error', `Ukuran berkas ${file.name} melebihi batas maksimal ${props.maxSizeMB || 5}MB.`);
            continue;
        }

        // Basic accept validation (can be more robust if needed)
        if (props.accept && !props.accept.includes(file.type)) {
            emit('error', `Format berkas ${file.name} tidak didukung.`);
            continue;
        }

        validFiles.push(file);
    }

    return validFiles;
};

const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    isDragging.value = false;
    
    if (props.disabled) return;

    if (e.dataTransfer?.files) {
        const validFiles = validateFiles(e.dataTransfer.files);
        if (validFiles.length > 0) {
            emit('update:modelValue', [...props.modelValue, ...validFiles]);
        }
    }
};

const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files) {
        const validFiles = validateFiles(target.files);
        if (validFiles.length > 0) {
            emit('update:modelValue', [...props.modelValue, ...validFiles]);
        }
    }
    // Reset input so the same file can be selected again if it was removed
    if (fileInput.value) fileInput.value.value = '';
};

const removeFile = (index: number) => {
    const newFiles = [...props.modelValue];
    newFiles.splice(index, 1);
    emit('update:modelValue', newFiles);
};

const formatSize = (bytes: number) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const isImage = (file: File) => file.type.startsWith('image/');

const createPreviewUrl = (file: File) => URL.createObjectURL(file);
</script>

<template>
    <div class="space-y-4">
        <div 
            class="relative rounded-lg border-2 border-dashed p-6 transition-colors"
            :class="[
                isDragging ? 'border-kominfo-primary bg-kominfo-primary/5' : 'border-slate-300 bg-white',
                disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer',
            ]"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            @click="!disabled && fileInput?.click()"
        >
            <input 
                type="file" 
                ref="fileInput" 
                class="hidden" 
                :multiple="multiple" 
                :accept="acceptTypes"
                :disabled="disabled"
                @change="handleFileSelect"
            />
            
            <div class="flex flex-col items-center justify-center space-y-2 text-center">
                <div class="rounded-full bg-slate-100 p-3">
                    <UploadCloud class="h-6 w-6 text-slate-500" />
                </div>
                <div class="text-sm">
                    <span class="font-semibold text-kominfo-primary">Klik untuk unggah</span> atau seret dan lepas
                </div>
                <p class="text-xs text-slate-500">
                    JPG, PNG atau PDF (Maks. {{ maxSizeMB || 5 }}MB)
                    <span v-if="multiple">- Maksimal {{ maxFilesAllowed }} berkas</span>
                </p>
            </div>
        </div>

        <!-- Preview Grid -->
        <div v-if="modelValue.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div 
                v-for="(file, index) in modelValue" 
                :key="index"
                class="group relative overflow-hidden rounded-lg border border-slate-200 bg-white p-2 flex items-center gap-3 pr-8"
            >
                <div class="h-12 w-12 shrink-0 overflow-hidden rounded bg-slate-100 flex items-center justify-center">
                    <img 
                        v-if="isImage(file)" 
                        :src="createPreviewUrl(file)" 
                        alt="preview" 
                        class="h-full w-full object-cover"
                    />
                    <FileIcon v-else class="h-6 w-6 text-slate-400" />
                </div>
                
                <div class="flex-1 overflow-hidden">
                    <p class="truncate text-sm font-medium text-slate-700" :title="file.name">
                        {{ file.name }}
                    </p>
                    <p class="text-xs text-slate-500">{{ formatSize(file.size) }}</p>
                </div>
                
                <button 
                    type="button"
                    @click.stop="removeFile(index)"
                    class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 opacity-0 hover:bg-slate-100 hover:text-red-500 transition-all group-hover:opacity-100"
                    :disabled="disabled"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>