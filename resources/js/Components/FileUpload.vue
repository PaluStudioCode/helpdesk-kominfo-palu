<script setup lang="ts">
import { ref, computed } from 'vue';
import { UploadCloud, X, File as FileIcon, Plus } from 'lucide-vue-next';
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
const acceptTypes = computed(() => props.accept || 'image/jpeg,image/png');

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
        emit('error', `Maksimal hanya dapat mengunggah ${maxFilesAllowed.value} gambar.`);
        return validFiles;
    }

    const maxSizeBytes = (props.maxSizeMB || 5) * 1024 * 1024;
    const allowedMimeTypes = acceptTypes.value.split(',').map(t => t.trim().toLowerCase());

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fileExt = file.name.split('.').pop()?.toLowerCase() || '';
        const isImageMime = allowedMimeTypes.some(mime => mime.includes('image/') ? file.type.startsWith('image/') : file.type === mime);
        const isImageExt = ['jpg', 'jpeg', 'png'].includes(fileExt);

        // Format validation with clear error message
        if (!isImageMime && !isImageExt) {
            emit('error', `File "${file.name}" ditolak! Format tidak didukung. Hanya file gambar (.jpg, .jpeg, .png) yang diperbolehkan.`);
            continue;
        }

        // File size validation with clear error message
        if (file.size > maxSizeBytes) {
            emit('error', `Ukuran gambar "${file.name}" terlalu besar (${formatSize(file.size)}). Batas maksimal adalah ${props.maxSizeMB || 5}MB.`);
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
    <div class="space-y-3">
        <!-- Hidden file input -->
        <input 
            type="file" 
            ref="fileInput" 
            class="hidden" 
            :multiple="multiple" 
            :accept="acceptTypes"
            :disabled="disabled"
            @change="handleFileSelect"
        />

        <!-- Big Dropzone: Only shown when no files uploaded yet -->
        <div 
            v-if="modelValue.length === 0"
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
            <div class="flex flex-col items-center justify-center space-y-2 text-center">
                <div class="rounded-full bg-slate-100 p-3">
                    <UploadCloud class="h-6 w-6 text-slate-500" />
                </div>
                <div class="text-sm">
                    <span class="font-semibold text-kominfo-primary">Klik untuk unggah</span> atau seret dan lepas
                </div>
                <p class="text-xs text-slate-500">
                    Format JPG, JPEG, atau PNG (Maks. {{ maxSizeMB || 5 }}MB)
                    <span v-if="multiple">- Maksimal {{ maxFilesAllowed }} gambar</span>
                </p>
            </div>
        </div>

        <!-- Preview Grid & Compact Add More Action -->
        <div v-else class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <div 
                    v-for="(file, index) in modelValue" 
                    :key="index"
                    class="group relative overflow-hidden rounded-lg border border-slate-200 bg-white p-2.5 flex items-center gap-3 pr-8 shadow-sm"
                >
                    <div class="h-11 w-11 shrink-0 overflow-hidden rounded bg-slate-100 flex items-center justify-center">
                        <img 
                            v-if="isImage(file)" 
                            :src="createPreviewUrl(file)" 
                            alt="preview" 
                            class="h-full w-full object-cover"
                        />
                        <FileIcon v-else class="h-5 w-5 text-slate-400" />
                    </div>
                    
                    <div class="flex-1 overflow-hidden">
                        <p class="truncate text-xs font-medium text-slate-800" :title="file.name">
                            {{ file.name }}
                        </p>
                        <p class="text-[11px] text-slate-500">{{ formatSize(file.size) }}</p>
                    </div>
                    
                    <button 
                        type="button"
                        @click.stop="removeFile(index)"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-all"
                        title="Hapus berkas"
                        :disabled="disabled"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Compact Add Button when limit not reached -->
            <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                <span>{{ modelValue.length }} dari {{ maxFilesAllowed }} berkas terpilih</span>
                <Button 
                    v-if="multiple && modelValue.length < maxFilesAllowed" 
                    type="button" 
                    variant="outline" 
                    size="sm" 
                    class="h-7 text-xs gap-1 border-slate-300"
                    :disabled="disabled"
                    @click="fileInput?.click()"
                >
                    <Plus class="h-3.5 w-3.5" /> Tambah Berkas
                </Button>
            </div>
        </div>
    </div>
</template>