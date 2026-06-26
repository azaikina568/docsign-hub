<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { getSigningContext, signWithToken, type SigningContext } from '@/shared/api/signing'
import { useAuthStore } from '@/stores/auth'
import { normalizeError } from '@/shared/api/errors'
import { toast } from '@/shared/toast'
import { formatDateTime, statusLabel } from '@/shared/format'
import PublicLayout from '@/layouts/PublicLayout.vue'
import AppButton from '@/components/AppButton.vue'
import StatusBadge from '@/features/documents/StatusBadge.vue'

const route = useRoute()
const auth = useAuthStore()
const token = route.params.token as string

const context = ref<SigningContext | null>(null)
const loading = ref(true)
const loadError = ref('')
const signing = ref(false)
const justSigned = ref(false)

async function load(): Promise<void> {
  loadError.value = ''
  try {
    context.value = await getSigningContext(token)
  } catch (error) {
    loadError.value = normalizeError(error).message
  } finally {
    loading.value = false
  }
}

onMounted(load)

const ctx = computed(() => context.value)
const isViewer = computed(() => ctx.value?.party.role === 'viewer')
const documentOpen = computed(
  () => ctx.value?.document.status === 'pending' || ctx.value?.document.status === 'partially_signed',
)
// Account-bound: подписать может только сам участник под своим логином (сверяем по email).
const loggedInAsParty = computed(() => auth.isAuthenticated && auth.user?.email === ctx.value?.party.email)
const percent = computed(() =>
  ctx.value && ctx.value.progress.total > 0 ? Math.round((ctx.value.progress.signed / ctx.value.progress.total) * 100) : 0,
)
const needsLogin = computed(
  () =>
    !!ctx.value &&
    ctx.value.requires_account &&
    !loggedInAsParty.value &&
    !ctx.value.already_signed &&
    documentOpen.value &&
    !ctx.value.expired &&
    ctx.value.your_turn &&
    !isViewer.value,
)
const canSign = computed(() => {
  const c = ctx.value
  if (!c || isViewer.value || c.already_signed || c.expired || !documentOpen.value || !c.your_turn) {
    return false
  }

  // can_sign из API покрывает внешнего участника; account-bound подписывает, если залогинен как он сам.
  return c.can_sign || (c.requires_account && loggedInAsParty.value)
})

async function onSign(): Promise<void> {
  signing.value = true

  try {
    await signWithToken(token)
    justSigned.value = true
    toast.success('Thank you! Your signature is recorded.')
    await load()
  } catch (error) {
    toast.error(normalizeError(error).message)
  } finally {
    signing.value = false
  }
}
</script>

<template>
  <PublicLayout>
    <p v-if="loading" class="text-center text-sm text-slate-400">Loading…</p>

    <div v-else-if="loadError" class="rounded-xl border border-slate-200 bg-white p-6 text-center">
      <p class="text-base font-medium text-slate-900">This signing link is invalid or no longer available.</p>
      <p class="mt-1 text-sm text-slate-500">Please check the link from your email, or ask the sender to resend it.</p>
    </div>

    <div v-else-if="ctx" class="rounded-xl border border-slate-200 bg-white p-6">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-lg font-semibold text-slate-900">{{ ctx.document.title }}</h1>
          <p class="mt-1 text-sm text-slate-500">
            You're invited as <span class="font-medium text-slate-700">{{ ctx.party.name }}</span>
            ({{ ctx.party.email }}) · {{ ctx.party.role }}
          </p>
        </div>
        <StatusBadge :status="ctx.document.status" />
      </div>

      <div class="mt-4">
        <div class="flex items-center justify-between text-sm text-slate-500">
          <span>Signing progress</span>
          <span>{{ ctx.progress.signed }} of {{ ctx.progress.total }} signed</span>
        </div>
        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-slate-900 transition-all" :style="{ width: `${percent}%` }" />
        </div>
        <p class="mt-2 text-xs text-slate-400">Deadline: {{ formatDateTime(ctx.document.expires_at) }}</p>
      </div>

      <div class="mt-5 border-t border-slate-100 pt-5">
        <p v-if="ctx.already_signed" class="text-sm text-green-600">
          <template v-if="justSigned">Thank you! Your signature is recorded</template>
          <template v-else>You have already signed this document</template>
          <span v-if="ctx.party.signed_at"> on {{ formatDateTime(ctx.party.signed_at) }}</span>.
        </p>

        <p v-else-if="isViewer" class="text-sm text-slate-500">
          You have view-only access to this document — no signature is required from you.
        </p>

        <p v-else-if="!documentOpen" class="text-sm text-slate-500">
          This document is no longer open for signing (status: {{ statusLabel(ctx.document.status) }}).
        </p>

        <p v-else-if="ctx.expired" class="text-sm text-red-600">The signing deadline has passed.</p>

        <p v-else-if="!ctx.your_turn" class="text-sm text-slate-500">
          It's not your turn yet — earlier signers must sign first. You'll be able to sign once it's your turn.
        </p>

        <div v-else-if="needsLogin" class="flex flex-col gap-2">
          <p class="text-sm text-slate-600">This document is tied to your account. Sign in as {{ ctx.party.email }} to sign.</p>
          <RouterLink
            :to="{ name: 'login', query: { redirect: route.fullPath } }"
            class="inline-flex w-fit rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
          >
            Sign in to continue
          </RouterLink>
        </div>

        <AppButton v-else-if="canSign" :loading="signing" @click="onSign">Sign this document</AppButton>
      </div>
    </div>
  </PublicLayout>
</template>
