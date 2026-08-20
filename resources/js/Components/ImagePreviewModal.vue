<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { X, Download, ChevronLeft, ChevronRight } from 'lucide-vue-next';

export interface PreviewImageItem {
    url: string;
    name?: string;
}

const props = defineProps<{
    open: boolean;
    images: PreviewImageItem[];
    initialIndex?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'close'): void;
}>();

const currentIndex = ref(props.initialIndex || 0);

watch(
    () => props.initialIndex,
    (newVal) => {
        if (typeof newVal === 'number' && newVal >= 0 && newVal < (props.images?.length || 0)) {
            currentIndex.value = newVal;
        }
    }
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
            if (typeof props.initialIndex === 'number') {
                currentIndex.value = props.initialIndex;
            }
        } else {
            document.body.style.overflow = '';
        }
    }
);

const currentImage = computed(() => {
    if (!props.images || props.images.length === 0) return null;
    const idx = Math.min(Math.max(currentIndex.value, 0), props.images.length - 1);
    return props.images[idx];
});

const hasMultiple = computed(() => (props.images?.length || 0) > 1);

const prev = () => {
    if (!props.images || props.images.length <= 1) return;
    if (currentIndex.value > 0) {
        currentIndex.value--;
    } else {
        currentIndex.value = props.images.length - 1; // loop back to end
    }
};

const next = () => {
    if (!props.images || props.images.length <= 1) return;
    if (currentIndex.value < props.images.length - 1) {
        currentIndex.value++;
    } else {
        currentIndex.value = 0; // loop back to start
    }
};

const close = () => {
    emit('update:open', false);
    emit('close');
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (!props.open) return;
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowLeft') {
        prev();
    } else if (e.key === 'ArrowRight') {
        next();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="open && currentImage" 
                class="fixed inset-0 z-[9999] flex flex-col items-center justify-center p-4 sm:p-6 bg-slate-950/85 backdrop-blur-md select-none"
                @click.self="close"
            >
                <!-- Top Toolbar -->
                <div class="absolute top-4 right-4 sm:top-6 sm:right-6 flex items-center gap-3 z-20">
                    <!-- Counter -->
                    <div v-if="hasMultiple" class="px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-sm text-xs font-semibold text-white tracking-wide border border-white/10 shadow-sm">
                        {{ currentIndex + 1 }} / {{ images.length }}
                    </div>

                    <a 
                        v-if="currentImage.url"
                        :href="currentImage.url" 
                        :download="currentImage.name || 'download-image'"
                        class="p-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors backdrop-blur-sm shadow-md"
                        title="Unduh Gambar Asli"
                        @click.stop
                    >
                        <Download class="w-5 h-5" />
                    </a>
                    <button 
                        type="button" 
                        @click="close"
                        class="p-2.5 rounded-full bg-white/10 hover:bg-rose-600/80 text-white transition-colors backdrop-blur-sm shadow-md cursor-pointer"
                        title="Tutup (Esc)"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Navigation Buttons (Prev / Next) -->
                <button 
                    v-if="hasMultiple"
                    type="button"
                    @click.stop="prev"
                    class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/25 text-white transition-all backdrop-blur-sm z-20 shadow-lg cursor-pointer hover:scale-105 active:scale-95"
                    title="Sebelumnya (Panah Kiri)"
                >
                    <ChevronLeft class="w-6 h-6" />
                </button>

                <button 
                    v-if="hasMultiple"
                    type="button"
                    @click.stop="next"
                    class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 p-3 rounded-full bg-white/10 hover:bg-white/25 text-white transition-all backdrop-blur-sm z-20 shadow-lg cursor-pointer hover:scale-105 active:scale-95"
                    title="Berikutnya (Panah Kanan)"
                >
                    <ChevronRight class="w-6 h-6" />
                </button>

                <!-- Main Image Container -->
                <Transition
                    name="fade-slide"
                    mode="out-in"
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div :key="currentImage.url" class="relative max-w-full max-h-[85vh] flex flex-col items-center" @click.stop>
                        <img 
                            :src="currentImage.url" 
                            :alt="currentImage.name || 'Preview Gambar'" 
                            class="max-w-[92vw] max-h-[78vh] sm:max-w-[85vw] sm:max-h-[80vh] object-contain rounded-xl shadow-2xl ring-1 ring-white/15"
                        />
                        <div v-if="currentImage.name" class="mt-3 px-3 py-1.5 rounded-lg bg-black/60 backdrop-blur-sm text-xs font-medium text-slate-200 max-w-md truncate text-center shadow">
                            {{ currentImage.name }}
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
