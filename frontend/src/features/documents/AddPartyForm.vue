<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useAddParty } from '@/features/documents/queries'
import type { PartyRole } from '@/shared/api/documents'
import { normalizeError } from '@/shared/api/errors'
import { toast } from '@/shared/toast'
import { partySchema, validate } from '@/shared/validation'
import FormField from '@/components/FormField.vue'
import AppButton from '@/components/AppButton.vue'

const props = defineProps<{ documentId: string }>()

const addParty = useAddParty(props.documentId)
const form = reactive<{ name: string; email: string; role: PartyRole }>({ name: '', email: '', role: 'signer' })
const errors = ref<Record<string, string>>({})
const generalError = ref('')

async function onSubmit(): Promise<void> {
  generalError.value = ''

  const clientErrors = validate(partySchema, { name: form.name, email: form.email })
  if (clientErrors) {
    errors.value = clientErrors
    return
  }

  errors.value = {}

  try {
    // signing_order не задаём — бэкенд сам присвоит следующий по порядку для signer'а.
    await addParty.mutateAsync({ name: form.name, email: form.email, role: form.role })
    form.name = ''
    form.email = ''
    form.role = 'signer'
    toast.success('Party added.')
  } catch (error) {
    const normalized = normalizeError(error)
    errors.value = normalized.fields
    generalError.value = Object.keys(normalized.fields).length ? '' : normalized.message
  }
}
</script>

<template>
  <form class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4" novalidate @submit.prevent="onSubmit">
    <p class="text-sm font-medium text-slate-700">Add a party</p>
    <p v-if="generalError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ generalError }}</p>

    <div class="grid gap-3 sm:grid-cols-2">
      <FormField id="party-name" v-model="form.name" label="Name" :error="errors.name" />
      <FormField id="party-email" v-model="form.email" label="Email" type="email" :error="errors.email" />
    </div>

    <div class="flex items-end gap-3">
      <div class="flex flex-col gap-1.5">
        <label for="party-role" class="text-sm font-medium text-slate-700">Role</label>
        <select
          id="party-role"
          v-model="form.role"
          class="rounded-md border border-slate-300 px-3 py-2 text-sm outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-900/10"
        >
          <option value="signer">Signer</option>
          <option value="viewer">Viewer</option>
        </select>
      </div>
      <AppButton type="submit" :loading="addParty.isPending.value">Add</AppButton>
    </div>
  </form>
</template>
