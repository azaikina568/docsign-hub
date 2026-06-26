<script setup lang="ts">
import { useRemoveParty } from '@/features/documents/queries'
import type { DocumentParty } from '@/shared/api/documents'

const props = defineProps<{ documentId: string; parties: DocumentParty[]; canManage: boolean }>()

const removeParty = useRemoveParty(props.documentId)
</script>

<template>
  <ul v-if="parties.length" class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
    <li v-for="party in parties" :key="party.id" class="flex items-center justify-between gap-3 px-4 py-3">
      <div class="min-w-0">
        <p class="truncate text-sm font-medium text-slate-900">
          {{ party.name }}
          <span v-if="party.role === 'signer'" class="ml-1 text-xs font-normal text-slate-400"
            >#{{ party.signing_order }}</span
          >
        </p>
        <p class="truncate text-xs text-slate-400">{{ party.email }}</p>
      </div>
      <div class="flex shrink-0 items-center gap-3">
        <span class="text-xs capitalize text-slate-500">{{ party.role }}</span>
        <span
          class="rounded-full px-2 py-0.5 text-xs font-medium"
          :class="party.status === 'signed' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
        >
          {{ party.status === 'signed' ? 'Signed' : 'Pending' }}
        </span>
        <button
          v-if="canManage"
          class="text-xs text-red-500 hover:underline disabled:opacity-40"
          :disabled="removeParty.isPending.value"
          @click="removeParty.mutate(party.id)"
        >
          Remove
        </button>
      </div>
    </li>
  </ul>
  <p v-else class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-400">
    No parties yet.
  </p>
</template>
