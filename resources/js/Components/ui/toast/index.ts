import type { ToastRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"

export { default as Toast } from "./Toast.vue"
export { default as ToastAction } from "./ToastAction.vue"
export { default as ToastClose } from "./ToastClose.vue"
export { default as ToastDescription } from "./ToastDescription.vue"
export { default as Toaster } from "./Toaster.vue"
export { default as ToastProvider } from "./ToastProvider.vue"
export { default as ToastTitle } from "./ToastTitle.vue"
export { default as ToastViewport } from "./ToastViewport.vue"
export { toast, useToast } from "./use-toast"

import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export const toastVariants = cva(
  "group pointer-events-auto relative flex w-full items-center justify-between space-x-3 overflow-hidden rounded-lg border p-4 shadow-lg transition-all data-[swipe=cancel]:translate-x-0 data-[swipe=end]:translate-x-(--reka-toast-swipe-end-x) data-[swipe=move]:translate-x-(--reka-toast-swipe-move-x) data-[swipe=move]:transition-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[swipe=end]:animate-out data-[state=closed]:fade-out-80 data-[state=closed]:slide-out-to-right-full data-[state=open]:slide-in-from-top-full data-[state=open]:sm:slide-in-from-top-full",
  {
    variants: {
      variant: {
        default: "border-slate-200 bg-white text-slate-900 shadow-slate-900/10",
        success: "border-emerald-200 bg-emerald-50 text-emerald-950 shadow-emerald-900/10",
        destructive: "border-rose-200 bg-rose-50 text-rose-950 shadow-rose-900/10",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

type ToastVariants = VariantProps<typeof toastVariants>

export interface ToastProps extends ToastRootProps {
  class?: HTMLAttributes["class"]
  variant?: ToastVariants["variant"]
  onOpenChange?: ((value: boolean) => void) | undefined
}
