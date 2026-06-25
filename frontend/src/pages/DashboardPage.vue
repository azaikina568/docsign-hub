<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'

const auth = useAuthStore()
</script>

<template>
  <AppLayout>
    <div
      v-if="!auth.emailVerified"
      class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
      Please verify your email to start creating documents. Check your inbox for the confirmation link.
    </div>

    <h1 class="text-xl font-semibold text-slate-900">Documents</h1>
    <p class="mt-1 text-sm text-slate-500">Welcome, {{ auth.user?.name }}.</p>

    <div class="mt-6 rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-400">
      Your documents list will appear here.
    </div>
  </AppLayout>
</template>
