<script setup lang="ts">
import { ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { listSigningRequests, signAsIdentity } from '@/shared/api/signing'
import { normalizeError } from '@/shared/api/errors'
import { formatDateTime } from '@/shared/format'
import AppLayout from '@/layouts/AppLayout.vue'
import AppButton from '@/components/AppButton.vue'
import StatusBadge from '@/features/documents/StatusBadge.vue'

const client = useQueryClient()
const page = ref(1)
const actionError = ref('')

const { data, isPending, isError } = useQuery({
  queryKey: ['signing-requests', page],
  queryFn: () => listSigningRequests(page.value),
})

const signMutation = useMutation({
  mutationFn: (partyId: number) => signAsIdentity(partyId),
  onSuccess: () => {
    // После подписи участие уходит из pending; могло измениться и состояние документа.
    void client.invalidateQueries({ queryKey: ['signing-requests'] })
    void client.invalidateQueries({ queryKey: ['documents', 'list'] })
  },
  onError: (error) => {
    actionError.value = normalizeError(error).message
  },
})

function sign(partyId: number): void {
  actionError.value = ''
  signMutation.mutate(partyId)
}
</script>

<template>
  <AppLayout>
    <h1 class="text-xl font-semibold text-slate-900">Awaiting your signature</h1>
    <p class="mt-1 text-sm text-slate-500">Documents where you're a signer and it's your turn or you're up next.</p>

    <p v-if="actionError" class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ actionError }}</p>

    <div v-if="isPending" class="mt-8 text-center text-sm text-slate-400">Loading…</div>
    <div v-else-if="isError" class="mt-8 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
      Could not load your signing requests.
    </div>
    <div
      v-else-if="data && data.data.length === 0"
      class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400"
    >
      Nothing to sign right now.
    </div>

    <ul v-else-if="data" class="mt-4 flex flex-col gap-2">
      <li
        v-for="req in data.data"
        :key="req.party_id"
        class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3"
      >
        <div class="min-w-0">
          <p class="truncate font-medium text-slate-900">{{ req.document.title }}</p>
          <p class="mt-0.5 text-xs text-slate-400">
            You're signer #{{ req.signing_order }} · deadline {{ formatDateTime(req.document.expires_at) }}
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-3">
          <StatusBadge :status="req.document.status" />
          <AppButton :loading="signMutation.isPending.value" @click="sign(req.party_id)">Sign</AppButton>
        </div>
      </li>
    </ul>

    <div v-if="data && data.meta.last_page > 1" class="mt-5 flex items-center justify-between text-sm">
      <button
        class="rounded-md px-3 py-1.5 text-slate-600 enabled:hover:bg-slate-100 disabled:opacity-40"
        :disabled="page <= 1"
        @click="page--"
      >
        Previous
      </button>
      <span class="text-slate-400">Page {{ data.meta.current_page }} of {{ data.meta.last_page }}</span>
      <button
        class="rounded-md px-3 py-1.5 text-slate-600 enabled:hover:bg-slate-100 disabled:opacity-40"
        :disabled="page >= data.meta.last_page"
        @click="page++"
      >
        Next
      </button>
    </div>
  </AppLayout>
</template>
