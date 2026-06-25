<script setup lang="ts">
import { toRef } from 'vue'
import { useEventsQuery } from '@/features/documents/queries'
import { statusLabel } from '@/shared/format'
import { formatDateTime } from '@/shared/format'

const props = defineProps<{ documentId: string }>()

const { data, isPending, isError } = useEventsQuery(toRef(props, 'documentId'))
</script>

<template>
  <div>
    <p v-if="isPending" class="text-sm text-slate-400">Loading history…</p>
    <p v-else-if="isError" class="text-sm text-red-600">Could not load history.</p>

    <ol v-else-if="data" class="relative ml-1.5 border-l border-slate-200">
      <li v-for="entry in data.data" :key="entry.id" class="ml-4 py-2">
        <span class="absolute -left-1.5 mt-1.5 size-3 rounded-full border-2 border-white bg-slate-300" />
        <p class="text-sm text-slate-700">
          <span v-if="entry.from_status">{{ statusLabel(entry.from_status) }} → </span>
          <span class="font-medium">{{ statusLabel(entry.to_status) }}</span>
        </p>
        <p v-if="entry.reason" class="text-xs text-slate-500">{{ entry.reason }}</p>
        <p class="text-xs text-slate-400">{{ formatDateTime(entry.created_at) }}</p>
      </li>
    </ol>
  </div>
</template>
