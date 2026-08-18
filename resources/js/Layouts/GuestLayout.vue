<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-slate-50 pt-12 sm:justify-center sm:pt-0"
    >
        <div class="mb-4 text-center">
            <Link href="/" class="flex flex-col items-center justify-center gap-2">
                <!-- Replace with Logo Kominfo later -->
                <div class="h-16 w-16 bg-kominfo-primary rounded-full flex items-center justify-center shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-8 py-8 shadow-xl border border-slate-100 sm:max-w-md sm:rounded-xl"
        >
            <slot />
        </div>
        
        <div class="mt-8 text-center text-sm text-slate-500">
            &copy; {{ new Date().getFullYear() }} Dinas Komunikasi dan Informatika.
        </div>
    </div>
</template>
