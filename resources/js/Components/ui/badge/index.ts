import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

export const badgeVariants = cva(
  "inline-flex gap-1 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-kominfo-primary text-white hover:bg-kominfo-primary-dark",
        secondary:
          "border-transparent bg-slate-100 text-slate-900 hover:bg-slate-200",
        destructive:
          "border-transparent bg-red-100 text-red-800 hover:bg-red-200",
        outline: "text-slate-900",
        // Custom variants for Ticket Lifecycle
        pending_admin: "border-transparent bg-blue-100 text-blue-800 font-medium",
        in_progress: "border-transparent bg-blue-50 text-blue-800 font-medium border border-blue-200",
        on_hold: "border-transparent bg-amber-100 text-amber-900 font-medium border border-amber-300",
        pending_approval: "border-transparent bg-purple-100 text-purple-800 font-medium",
        closed: "border-transparent bg-emerald-100 text-emerald-800 font-medium",
        cancelled: "border-transparent bg-rose-100 text-rose-800 font-medium",
        // Legacy fallbacks
        open: "border-transparent bg-blue-100 text-blue-800",
        resolved: "border-transparent bg-purple-100 text-purple-800",
        // SLA Status
        sla_safe: "bg-emerald-50 text-emerald-700 border-emerald-200",
        sla_warning: "bg-amber-50 text-amber-700 border-amber-200",
        sla_danger: "bg-red-100 text-red-800 animate-pulse border-red-200",
        sla_completed: "bg-emerald-50 text-emerald-700 border-emerald-200",
        // Networks
        fiber_optic: "border-transparent bg-sky-100 text-sky-800",
        perangkat_akses: "border-transparent bg-indigo-100 text-indigo-800",
        power_poe: "border-transparent bg-amber-100 text-amber-800",
        converter: "border-transparent bg-pink-100 text-pink-800",
        layanan_jaringan: "border-transparent bg-emerald-100 text-emerald-800",
        lan: "border-transparent bg-indigo-100 text-indigo-800",
        wifi: "border-transparent bg-emerald-100 text-emerald-800",
        // Priority
        priority_low: "border-transparent bg-slate-100 text-slate-700",
        priority_medium: "border-transparent bg-blue-100 text-blue-700",
        priority_high: "border-transparent bg-orange-100 text-orange-700",
        priority_emergency: "border-transparent bg-red-100 text-red-700",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type BadgeVariants = VariantProps<typeof badgeVariants>

