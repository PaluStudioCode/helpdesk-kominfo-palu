<script setup lang="ts">
import type { ToastProviderProps } from "reka-ui"
import { ToastProvider } from "reka-ui"

const props = defineProps<ToastProviderProps>()
</script>

<template>
  <ToastProvider :duration="5000" v-bind="props">
    <slot />
  </ToastProvider>
</template>
