<script setup lang="ts">
import ToastHost from '@/components/ToastHost.vue'
</script>

<template>
  <RouterView v-slot="{ Component, route }">
    <component :is="Component" :key="route.path" />
  </RouterView>
  <!-- Вне keyed RouterView — тосты переживают навигацию между страницами. -->
  <ToastHost />
</template>
