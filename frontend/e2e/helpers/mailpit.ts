import type { APIRequestContext } from '@playwright/test'

const MAILPIT = process.env.MAILPIT_URL ?? 'http://localhost:8025'

interface MailpitMessage {
  ID: string
  To: Array<{ Address: string }>
}

async function latestMessageId(request: APIRequestContext, address: string): Promise<string | null> {
  const res = await request.get(`${MAILPIT}/api/v1/messages`)
  const json = (await res.json()) as { messages?: MailpitMessage[] }

  for (const message of json.messages ?? []) {
    if ((message.To ?? []).some((to) => to.Address === address)) {
      return message.ID
    }
  }

  return null
}

async function messageBody(request: APIRequestContext, id: string): Promise<string> {
  const res = await request.get(`${MAILPIT}/api/v1/message/${id}`)
  const json = (await res.json()) as { Text?: string; HTML?: string }

  // В HTML-письме `&` экранирован как `&amp;` — раскодируем, чтобы query подписи остался валидным.
  return `${json.Text ?? ''} ${json.HTML ?? ''}`.replace(/&amp;/g, '&')
}

/**
 * Ждёт письмо для адреса и возвращает совпавший по шаблону путь/ссылку. Доставка асинхронна
 * (регистрация → queue-worker, приглашения → outbox → publisher → consumer), поэтому опрашиваем Mailpit.
 */
export async function waitForLink(
  request: APIRequestContext,
  address: string,
  pattern: RegExp,
  timeoutMs = 45_000,
): Promise<string> {
  const deadline = Date.now() + timeoutMs

  while (Date.now() < deadline) {
    const id = await latestMessageId(request, address)

    if (id) {
      const match = (await messageBody(request, id)).match(pattern)

      if (match) {
        return match[0]
      }
    }

    await new Promise((resolve) => setTimeout(resolve, 1000))
  }

  throw new Error(`No email matching ${pattern} for ${address} within ${timeoutMs}ms`)
}
