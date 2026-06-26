import { expect, test } from '@playwright/test'

// Быстрый смоук гарда роутинга: приватная страница недоступна без сессии.
test('unauthenticated visit is redirected to login', async ({ page }) => {
  await page.goto('/')

  await expect(page).toHaveURL(/\/login(\?|$)/)
  await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible()
})
