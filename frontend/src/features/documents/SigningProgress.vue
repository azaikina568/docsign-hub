<script setup lang="ts">
import { computed } from 'vue'
import type { DocumentParty } from '@/shared/api/documents'

const props = defineProps<{ parties: DocumentParty[]; status: string }>()

// Индикатор очереди подписания (OPEN_QUESTIONS Q3): сколько подписали и чья очередь сейчас.
// Владелец видит конкретного участника — это его документ, скрывать не от кого.
const signers = computed(() =>
  props.parties.filter((p) => p.role === 'signer').sort((a, b) => (a.signing_order ?? 0) - (b.signing_order ?? 0)),
)
const signed = computed(() => signers.value.filter((p) => p.status === 'signed').length)
const total = computed(() => signers.value.length)
const percent = computed(() => (total.value === 0 ? 0 : Math.round((signed.value / total.value) * 100)))
const isOpen = computed(() => props.status === 'pending' || props.status === 'partially_signed')
const current = computed(() => (isOpen.value ? (signers.value.find((p) => p.status !== 'signed') ?? null) : null))
</script>

<template>
  <div v-if="total > 0" class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between text-sm">
      <span class="font-medium text-slate-700">Signing progress</span>
      <span class="text-slate-500">{{ signed }} of {{ total }} signed</span>
    </div>

    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-slate-900 transition-all" :style="{ width: `${percent}%` }" />
    </div>

    <p v-if="current" class="mt-3 text-sm text-slate-500">
      Waiting on
      <span class="font-medium text-slate-700">{{ current.name }}</span>
      <span class="text-slate-400">(#{{ current.signing_order }})</span>
    </p>
    <p v-else-if="status === 'signed'" class="mt-3 text-sm text-green-600">All signers have signed.</p>
  </div>
</template>
