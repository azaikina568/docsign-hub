import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    watch: {
      // В Docker на Windows file-события не доходят через bind-mount → HMR не видит правок. Включаем polling
      // только по флагу (его ставит docker-compose для фронта); на хосте нативный watch, polling не нужен.
      usePolling: process.env.VITE_USE_POLLING === 'true',
    },
  },
  test: {
    environment: 'happy-dom',
    setupFiles: ['./tests/setup.ts'],
    // Только юнит/компонентные specs из src; e2e (./e2e) гоняет Playwright, не Vitest.
    include: ['src/**/*.spec.ts'],
    // CSS не нужен в happy-dom-тестах: Tailwind-классы проверяем по имени, не по вычисленному стилю.
    css: false,
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,vue}'],
      // Точки входа/типы/роутер — без собственной логики, покрывать нечего.
      exclude: ['src/main.ts', 'src/router/**', 'src/**/types.ts', 'src/**/*.spec.ts'],
    },
  },
})
