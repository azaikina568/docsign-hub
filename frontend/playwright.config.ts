import { defineConfig, devices } from '@playwright/test'

// e2e гоняются против живого бэкенда (`make up`): API на :8080, Mailpit на :8025. Фронт Playwright
// поднимает сам (webServer ниже) на :5174 — так тестируется текущий исходник, а не контейнерный dev-сервер
// (его file-watch через Windows-bind-mount пропускает правки). Письма ведут на FRONTEND_URL, но из них берётся
// только путь (`/verify-email/...`, `/signing/...`), который открывается относительно baseURL.
const baseURL = process.env.E2E_BASE_URL ?? 'http://localhost:5174'

export default defineConfig({
  testDir: './e2e',
  // Полный сценарий идёт через асинхронную доставку писем (queue/consumer) — шаги ждут письмо, тест длинный.
  timeout: 120_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  // Поднимаем vite dev на текущем исходнике; API берётся из дефолта клиента (http://localhost:8080/api/v1).
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command: 'npm run dev -- --port 5174 --strictPort',
        url: 'http://localhost:5174',
        timeout: 120_000,
        reuseExistingServer: !process.env.CI,
      },
})
