<script setup lang="ts">
defineProps<{
    class?: string;
}>();
</script>

<template>
    <img 
        src="/storage/logo.png" 
        alt="Logo Kominfo Kota Palu" 
        :class="class || 'h-10 w-auto object-contain'" 
    />
</template>
