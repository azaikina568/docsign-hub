import { api } from './client'
import type { ResourceResponse } from './types'

export type DocumentStatus = 'draft' | 'pending' | 'partially_signed' | 'signed' | 'cancelled' | 'expired'
export type PartyRole = 'signer' | 'viewer'
export type PartyStatus = 'pending' | 'signed'

export interface DocumentParty {
  id: number
  name: string
  email: string
  role: PartyRole
  signing_order: number | null
  status: PartyStatus
  signed_at: string | null
}

export interface DocumentSummary {
  id: string
  title: string
  status: DocumentStatus
  expires_at: string | null
  completed_at: string | null
  parties_count?: number
  created_at: string | null
  updated_at: string | null
}

export interface DocumentDetail extends DocumentSummary {
  parties: DocumentParty[]
}

export interface StatusHistoryEntry {
  id: number
  from_status: DocumentStatus | null
  to_status: DocumentStatus
  reason: string | null
  changed_by_user_id: number | null
  created_at: string | null
}

// Конверт пагинации Laravel (resource collection).
export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
  links: { first: string | null; last: string | null; prev: string | null; next: string | null }
}

export interface CreateDocumentPayload {
  title: string
  expires_at?: string | null
}

export interface AddPartyPayload {
  name: string
  email: string
  role?: PartyRole
  signing_order?: number | null
}

export async function listDocuments(params: { status?: DocumentStatus; page?: number } = {}): Promise<Paginated<DocumentSummary>> {
  const { data } = await api.get<Paginated<DocumentSummary>>('/documents', { params })

  return data
}

export async function getDocument(id: string): Promise<DocumentDetail> {
  const { data } = await api.get<ResourceResponse<DocumentDetail>>(`/documents/${id}`)

  return data.data
}

export async function createDocument(payload: CreateDocumentPayload): Promise<DocumentDetail> {
  const { data } = await api.post<ResourceResponse<DocumentDetail>>('/documents', payload)

  return data.data
}

export async function deleteDocument(id: string): Promise<void> {
  await api.delete(`/documents/${id}`)
}

export async function sendDocument(id: string): Promise<DocumentDetail> {
  const { data } = await api.post<ResourceResponse<DocumentDetail>>(`/documents/${id}/send`)

  return data.data
}

export async function cancelDocument(id: string, reason?: string): Promise<DocumentDetail> {
  const { data } = await api.post<ResourceResponse<DocumentDetail>>(`/documents/${id}/cancel`, { reason })

  return data.data
}

export async function extendDeadline(id: string, expiresAt: string): Promise<DocumentDetail> {
  const { data } = await api.patch<ResourceResponse<DocumentDetail>>(`/documents/${id}/deadline`, { expires_at: expiresAt })

  return data.data
}

export async function addParty(id: string, payload: AddPartyPayload): Promise<DocumentParty> {
  const { data } = await api.post<ResourceResponse<DocumentParty>>(`/documents/${id}/parties`, payload)

  return data.data
}

export async function removeParty(id: string, partyId: number): Promise<void> {
  await api.delete(`/documents/${id}/parties/${partyId}`)
}

export async function listEvents(id: string, params: { sort?: 'asc' | 'desc'; page?: number } = {}): Promise<Paginated<StatusHistoryEntry>> {
  const { data } = await api.get<Paginated<StatusHistoryEntry>>(`/documents/${id}/events`, { params })

  return data
}
