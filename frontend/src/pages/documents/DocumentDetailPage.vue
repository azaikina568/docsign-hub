<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  useCancelDocument,
  useDeleteDocument,
  useDocumentQuery,
  useDuplicateDocument,
  useExtendDeadline,
  useSendDocument,
} from '@/features/documents/queries'
import { normalizeError } from '@/shared/api/errors'
import { toast } from '@/shared/toast'
import { formatDateTime } from '@/shared/format'
import AppLayout from '@/layouts/AppLayout.vue'
import AppButton from '@/components/AppButton.vue'
import StatusBadge from '@/features/documents/StatusBadge.vue'
import SigningProgress from '@/features/documents/SigningProgress.vue'
import PartyList from '@/features/documents/PartyList.vue'
import AddPartyForm from '@/features/documents/AddPartyForm.vue'
import DocumentTimeline from '@/features/documents/DocumentTimeline.vue'

const route = useRoute()
const router = useRouter()
// RouterView пересоздаёт страницу по route.path (см. App.vue), поэтому id стабилен на время жизни компонента.
const id = route.params.id as string

const { data: document, isPending, isError } = useDocumentQuery(ref(id))
const sendDoc = useSendDocument(id)
const cancelDoc = useCancelDocument(id)
const deleteDoc = useDeleteDocument(id)
const extendDoc = useExtendDeadline(id)
const duplicateDoc = useDuplicateDocument()

const isDraft = computed(() => document.value?.status === 'draft')
const isOpen = computed(() => document.value?.status === 'pending' || document.value?.status === 'partially_signed')
// Терминальные документы неизменны — их можно только продублировать в новый draft.
const isTerminal = computed(
  () =>
    document.value?.status === 'signed' ||
    document.value?.status === 'cancelled' ||
    document.value?.status === 'expired',
)
const hasSigner = computed(() => document.value?.parties.some((p) => p.role === 'signer') ?? false)

const cancelOpen = ref(false)
const cancelReason = ref('')
const extendOpen = ref(false)
const newDeadline = ref('')

// Действие + единое уведомление: успех/ошибку показываем тостом, без дублирующего инлайна.
async function run(fn: () => Promise<unknown>, successMessage: string): Promise<void> {
  try {
    await fn()
    toast.success(successMessage)
  } catch (error) {
    toast.error(normalizeError(error).message)
  }
}

async function onSend(): Promise<void> {
  if (!hasSigner.value) {
    toast.error('Add at least one signer before sending.')
    return
  }
  await run(() => sendDoc.mutateAsync(undefined), 'Document sent for signing.')
}

async function onCancel(): Promise<void> {
  await run(() => cancelDoc.mutateAsync(cancelReason.value || undefined), 'Document cancelled.')
  cancelOpen.value = false
}

async function onExtend(): Promise<void> {
  if (!newDeadline.value) {
    return
  }
  await run(() => extendDoc.mutateAsync(new Date(newDeadline.value).toISOString()), 'Deadline moved.')
  extendOpen.value = false
}

async function onDelete(): Promise<void> {
  await run(async () => {
    await deleteDoc.mutateAsync()
    await router.push({ name: 'dashboard' })
  }, 'Draft deleted.')
}

async function onDuplicate(): Promise<void> {
  if (!document.value) {
    return
  }
  try {
    const draft = await duplicateDoc.mutateAsync(document.value)
    toast.success('Created a new draft from this document.')
    await router.push({ name: 'document-detail', params: { id: draft.id } })
  } catch (error) {
    toast.error(normalizeError(error).message)
  }
}
</script>

<template>
  <AppLayout>
    <RouterLink :to="{ name: 'dashboard' }" class="text-sm text-slate-500 hover:underline"
      >← Back to documents</RouterLink
    >

    <div v-if="isPending" class="mt-8 text-center text-sm text-slate-400">Loading document…</div>
    <div v-else-if="isError || !document" class="mt-8 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
      Could not load this document.
    </div>

    <template v-else>
      <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-slate-900">{{ document.title }}</h1>
          <p class="mt-1 text-sm text-slate-400">
            Deadline: {{ formatDateTime(document.expires_at) }}
            <span v-if="document.completed_at"> · completed {{ formatDateTime(document.completed_at) }}</span>
          </p>
        </div>
        <StatusBadge :status="document.status" data-testid="doc-status" />
      </div>

      <!-- Действия зависят от стадии: draft — состав и отправка; открытый — отмена/продление; терминальный — дублирование. -->
      <div class="mt-4 flex flex-wrap gap-2">
        <AppButton v-if="isDraft" :loading="sendDoc.isPending.value" @click="onSend">Send for signing</AppButton>
        <AppButton v-if="isDraft" variant="ghost" @click="onDelete">Delete draft</AppButton>
        <AppButton v-if="isOpen" variant="ghost" @click="cancelOpen = !cancelOpen">Cancel</AppButton>
        <AppButton v-if="isOpen" variant="ghost" @click="extendOpen = !extendOpen">Extend deadline</AppButton>
        <AppButton v-if="isTerminal" :loading="duplicateDoc.isPending.value" @click="onDuplicate">
          Duplicate
        </AppButton>
      </div>
      <p v-if="isTerminal" class="mt-2 text-xs text-slate-400">
        This document is finalised and can’t be changed. Duplicate it to start a new draft with the same parties.
      </p>

      <div
        v-if="cancelOpen"
        class="mt-3 flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4 sm:max-w-md"
      >
        <label for="cancel-reason" class="text-sm font-medium text-slate-700">Reason (optional)</label>
        <textarea
          id="cancel-reason"
          v-model="cancelReason"
          rows="2"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
        />
        <AppButton :loading="cancelDoc.isPending.value" @click="onCancel">Confirm cancellation</AppButton>
      </div>

      <div
        v-if="extendOpen"
        class="mt-3 flex flex-col gap-2 rounded-lg border border-slate-200 bg-white p-4 sm:max-w-md"
      >
        <label for="new-deadline" class="text-sm font-medium text-slate-700">New deadline</label>
        <input
          id="new-deadline"
          v-model="newDeadline"
          type="datetime-local"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
        />
        <AppButton :loading="extendDoc.isPending.value" @click="onExtend">Move deadline</AppButton>
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
          <SigningProgress :parties="document.parties" :status="document.status" />

          <section>
            <h2 class="mb-2 text-sm font-semibold text-slate-700">Parties</h2>
            <PartyList :document-id="id" :parties="document.parties" :can-manage="isDraft" />
            <div v-if="isDraft" class="mt-3">
              <AddPartyForm :document-id="id" />
            </div>
          </section>
        </div>

        <section>
          <h2 class="mb-2 text-sm font-semibold text-slate-700">History</h2>
          <DocumentTimeline :document-id="id" />
        </section>
      </div>
    </template>
  </AppLayout>
</template>
