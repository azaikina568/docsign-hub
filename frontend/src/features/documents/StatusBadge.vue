<script setup lang="ts">
import { computed } from 'vue'
import type { DocumentStatus } from '@/shared/api/documents'
import { statusLabel } from '@/shared/format'

const props = defineProps<{ status: DocumentStatus }>()

const styles: Record<DocumentStatus, string> = {
  draft: 'bg-slate-100 text-slate-600',
  pending: 'bg-blue-100 text-blue-700',
  partially_signed: 'bg-amber-100 text-amber-700',
  signed: 'bg-green-100 text-green-700',
  cancelled: 'bg-slate-200 text-slate-500',
  expired: 'bg-red-100 text-red-700',
}

const classes = computed(() => styles[props.status])
</script>

<template>
  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="classes">
    {{ statusLabel(status) }}
  </span>
</template>
