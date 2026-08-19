<script setup lang="ts">
import type { ToastViewportProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ToastViewport } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ToastViewportProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <ToastViewport v-bind="delegatedProps" :class="cn('fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-auto sm:right-4 sm:top-4 sm:flex-col md:max-w-[420px]', props.class)" />
</template>
