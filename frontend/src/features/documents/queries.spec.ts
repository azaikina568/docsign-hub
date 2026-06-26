import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { http, HttpResponse } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../../../tests/msw/server'
import { useDuplicateDocument } from './queries'
import type { DocumentDetail } from '@/shared/api/documents'

const BASE = 'http://localhost:8080/api/v1'

// Гоняем композабл внутри смонтированного компонента: useMutation/useQueryClient требуют активный QueryClient.
function withQuery<T>(composable: () => T): T {
  let result!: T
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false }, queries: { retry: false } } })
  mount(
    defineComponent({
      setup() {
        result = composable()
        return () => h('div')
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient }]] } },
  )
  return result
}

const source: DocumentDetail = {
  id: '01SOURCE',
  title: 'Mutual NDA',
  status: 'cancelled',
  expires_at: null,
  completed_at: null,
  created_at: null,
  updated_at: null,
  parties: [
    {
      id: 2,
      name: 'Bob',
      email: 'bob@example.com',
      role: 'signer',
      signing_order: 2,
      status: 'pending',
      signed_at: null,
    },
    {
      id: 1,
      name: 'Alice',
      email: 'alice@example.com',
      role: 'signer',
      signing_order: 1,
      status: 'pending',
      signed_at: null,
    },
    {
      id: 3,
      name: 'Val',
      email: 'val@example.com',
      role: 'viewer',
      signing_order: null,
      status: 'pending',
      signed_at: null,
    },
  ],
}

describe('useDuplicateDocument', () => {
  it('creates a new draft from the title and copies parties in signing order', async () => {
    const created: Array<Record<string, unknown>> = []
    server.use(
      http.post(`${BASE}/documents`, async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({
          data: { ...source, id: '01DRAFT', status: 'draft', title: body.title, parties: [] },
        })
      }),
      http.post(`${BASE}/documents/01DRAFT/parties`, async ({ request }) => {
        const body = (await request.json()) as Record<string, unknown>
        created.push(body)
        return HttpResponse.json({ data: { id: created.length, status: 'pending', signed_at: null, ...body } })
      }),
    )

    const duplicate = withQuery(() => useDuplicateDocument())
    const draft = await duplicate.mutateAsync(source)

    expect(draft.id).toBe('01DRAFT')
    expect(draft.status).toBe('draft')
    // Порядок участников сохранён по signing_order (viewer без order идёт первым как 0).
    expect(created.map((p) => p.email)).toEqual(['val@example.com', 'alice@example.com', 'bob@example.com'])
    expect(created.map((p) => p.role)).toEqual(['viewer', 'signer', 'signer'])
  })
})
