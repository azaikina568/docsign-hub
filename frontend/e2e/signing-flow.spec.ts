import { expect, test } from '@playwright/test'
import { waitForLink } from './helpers/mailpit'

// Сквозной сценарий против живого стека: регистрация → верификация email из письма → черновик →
// участники → отправка → поэтапная подпись (приглашения приходят по очереди через consumer) → signed.
// Уникальные адреса на прогон, чтобы письма не путались с прошлыми запусками.
test('owner registers, verifies email and drives a document to fully signed', async ({ page, browser, request }) => {
  const ts = Date.now()
  const owner = `e2e.owner.${ts}@docsign.test`
  const alice = `e2e.alice.${ts}@example.com`
  const bob = `e2e.bob.${ts}@example.com`
  const password = 'password123'

  // 1. Регистрация — после неё пользователь залогинен и попадает в приложение.
  await page.goto('/register')
  await page.getByLabel('Name').fill('E2E Owner')
  await page.getByLabel('Email').fill(owner)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByLabel('Confirm password').fill(password)
  await page.getByRole('button', { name: 'Create account' }).click()
  // Регистрация — самый тяжёлый первый запрос (создание юзера + пара токенов + событие); ждём с запасом.
  await expect(page.getByRole('button', { name: 'Sign out' })).toBeVisible({ timeout: 30_000 })

  // 2. Подтверждение email по ссылке из письма (queue-worker доставляет его в Mailpit).
  const verifyPath = await waitForLink(request, owner, /\/verify-email\/[^\s"'<>]+/)
  await page.goto(verifyPath)
  await expect(page.getByText(/verified/i)).toBeVisible()

  // 3. Создание черновика — кнопка появляется только после верификации.
  await page.goto('/')
  await page.getByRole('link', { name: 'New document' }).click()
  await page.getByLabel('Title').fill(`E2E NDA ${ts}`)
  await page.getByRole('button', { name: 'Create draft' }).click()
  // Ждём именно детальную черновика (форма участников), а не /documents/new — иначе поймаем URL создания.
  await expect(page.getByText('Add a party')).toBeVisible()
  const documentUrl = page.url()

  // 4. Два подписанта по порядку добавления (signing_order бэкенд присвоит сам).
  for (const [name, email] of [
    ['Alice One', alice],
    ['Bob Two', bob],
  ]) {
    await page.getByLabel('Name').fill(name)
    await page.getByLabel('Email').fill(email)
    await page.selectOption('#party-role', 'signer')
    await page.getByRole('button', { name: 'Add', exact: true }).click()
    await expect(page.getByText(email)).toBeVisible()
  }

  // 5. Отправка: draft → pending, приглашение уходит только первому по очереди.
  await page.getByRole('button', { name: 'Send for signing' }).click()
  await expect(page.getByTestId('doc-status')).toHaveText('Pending')

  // 6. Alice подписывает по ссылке из письма. Подписант — анонимный посетитель, поэтому отдельный контекст.
  const signerContext = await browser.newContext()
  const aliceLink = await waitForLink(request, alice, /\/signing\/[A-Za-z0-9]+/)
  const alicePage = await signerContext.newPage()
  await alicePage.goto(aliceLink)
  await alicePage.getByRole('button', { name: 'Sign this document' }).click()
  // Подтверждение есть и в тосте (span), и инлайн (p) — берём устойчивый инлайн, тост авто-исчезает.
  await expect(alicePage.locator('p', { hasText: /your signature is recorded/i })).toBeVisible()
  await alicePage.close()

  // 7. Bob получает приглашение только теперь (поэтапно, на событие document.signed) и подписывает.
  const bobLink = await waitForLink(request, bob, /\/signing\/[A-Za-z0-9]+/)
  const bobPage = await signerContext.newPage()
  await bobPage.goto(bobLink)
  await bobPage.getByRole('button', { name: 'Sign this document' }).click()
  await expect(bobPage.locator('p', { hasText: /your signature is recorded/i })).toBeVisible()
  await signerContext.close()

  // 8. Владелец видит завершённый документ.
  await page.goto(documentUrl)
  await expect(page.getByTestId('doc-status')).toHaveText('Signed')
})
