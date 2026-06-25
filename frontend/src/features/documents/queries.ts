import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { Ref } from 'vue'
import * as docs from '@/shared/api/documents'
import type { AddPartyPayload, CreateDocumentPayload, DocumentStatus } from '@/shared/api/documents'

// Ключи кэша в одном месте — чтобы инвалидация после мутаций была точечной и не разъезжалась.
// Списки инвалидируем по префиксу ['documents','list'] (vue-query матчит частично).
export const documentKeys = {
  all: ['documents'] as const,
  lists: ['documents', 'list'] as const,
  detail: (id: string) => ['documents', 'detail', id] as const,
  events: (id: string) => ['documents', 'events', id] as const,
}

// Реактивные ключи: vue-query разворачивает ref'ы в queryKey и сам перезапрашивает при их смене.
export function useDocumentsQuery(status: Ref<DocumentStatus | undefined>, page: Ref<number>) {
  return useQuery({
    queryKey: ['documents', 'list', status, page],
    queryFn: () => docs.listDocuments({ status: status.value, page: page.value }),
    // При смене страницы показываем прошлые данные, пока грузятся новые (без «прыжка» в loading).
    placeholderData: keepPreviousData,
  })
}

export function useDocumentQuery(id: Ref<string>) {
  return useQuery({
    queryKey: ['documents', 'detail', id],
    queryFn: () => docs.getDocument(id.value),
  })
}

export function useEventsQuery(id: Ref<string>) {
  return useQuery({
    queryKey: ['documents', 'events', id],
    queryFn: () => docs.listEvents(id.value, { sort: 'desc' }),
  })
}

export function useCreateDocument() {
  const client = useQueryClient()

  return useMutation({
    mutationFn: (payload: CreateDocumentPayload) => docs.createDocument(payload),
    onSuccess: () => client.invalidateQueries({ queryKey: documentKeys.lists }),
  })
}

// Мутации над одним документом инвалидируют его detail/events и списки (статус/состав мог поменяться).
function useDocumentMutation<TArgs>(id: string, fn: (args: TArgs) => Promise<unknown>) {
  const client = useQueryClient()

  return useMutation({
    mutationFn: fn,
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: documentKeys.detail(id) })
      void client.invalidateQueries({ queryKey: documentKeys.events(id) })
      void client.invalidateQueries({ queryKey: documentKeys.lists })
    },
  })
}

export function useSendDocument(id: string) {
  return useDocumentMutation(id, () => docs.sendDocument(id))
}

export function useCancelDocument(id: string) {
  return useDocumentMutation(id, (reason: string | undefined) => docs.cancelDocument(id, reason))
}

export function useExtendDeadline(id: string) {
  return useDocumentMutation(id, (expiresAt: string) => docs.extendDeadline(id, expiresAt))
}

export function useAddParty(id: string) {
  return useDocumentMutation(id, (payload: AddPartyPayload) => docs.addParty(id, payload))
}

export function useRemoveParty(id: string) {
  return useDocumentMutation(id, (partyId: number) => docs.removeParty(id, partyId))
}

export function useDeleteDocument(id: string) {
  const client = useQueryClient()

  return useMutation({
    mutationFn: () => docs.deleteDocument(id),
    onSuccess: () => client.invalidateQueries({ queryKey: documentKeys.lists }),
  })
}
