<script setup lang="ts">
import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDocumentsQuery } from '@/features/documents/queries'
import type { DocumentStatus } from '@/shared/api/documents'
import { formatDate, isExpiringSoon } from '@/shared/format'
import AppLayout from '@/layouts/AppLayout.vue'
import StatusBadge from '@/features/documents/StatusBadge.vue'

const auth = useAuthStore()

const statuses: { value: DocumentStatus | undefined; label: string }[] = [
  { value: undefined, label: 'All' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending', label: 'Pending' },
  { value: 'partially_signed', label: 'Partially signed' },
  { value: 'signed', label: 'Signed' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'expired', label: 'Expired' },
]

const status = ref<DocumentStatus | undefined>(undefined)
const page = ref(1)

// Смена фильтра возвращает на первую страницу (иначе можно «зависнуть» на пустой странице).
watch(status, () => (page.value = 1))

const { data, isPending, isError, isPlaceholderData } = useDocumentsQuery(status, page)
</script>

<template>
  <AppLayout>
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold text-slate-900">Documents</h1>
      <RouterLink
        v-if="auth.emailVerified"
        :to="{ name: 'document-create' }"
        class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
      >
        New document
      </RouterLink>
      <!-- Не прячем кнопку у неподтверждённых, а показываем, почему она недоступна (см. баннер в layout). -->
      <span
        v-else
        class="cursor-not-allowed rounded-md bg-slate-100 px-4 py-2 text-sm font-medium text-slate-400"
        title="Verify your email first to create documents"
      >
        New document
      </span>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <button
        v-for="option in statuses"
        :key="option.label"
        class="rounded-full px-3 py-1 text-sm transition"
        :class="status === option.value ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
        @click="status = option.value"
      >
        {{ option.label }}
      </button>
    </div>

    <div v-if="isPending" class="mt-8 text-center text-sm text-slate-400">Loading documents…</div>
    <div v-else-if="isError" class="mt-8 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">
      Could not load documents. Please try again.
    </div>
    <div
      v-else-if="data && data.data.length === 0"
      class="mt-8 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-400"
    >
      No documents here yet.
    </div>

    <ul v-else-if="data" class="mt-4 flex flex-col gap-2" :class="{ 'opacity-60': isPlaceholderData }">
      <li v-for="doc in data.data" :key="doc.id">
        <RouterLink
          :to="{ name: 'document-detail', params: { id: doc.id } }"
          class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3 transition hover:border-slate-300 hover:shadow-sm"
        >
          <div class="min-w-0">
            <p class="truncate font-medium text-slate-900">{{ doc.title }}</p>
            <p class="mt-0.5 text-xs text-slate-400">
              {{ doc.parties_count ?? 0 }} {{ (doc.parties_count ?? 0) === 1 ? 'party' : 'parties' }}
              · created {{ formatDate(doc.created_at) }}
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-2">
            <span v-if="isExpiringSoon(doc.status, doc.expires_at)" class="text-xs font-medium text-amber-600">
              Expires soon
            </span>
            <StatusBadge :status="doc.status" />
          </div>
        </RouterLink>
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
