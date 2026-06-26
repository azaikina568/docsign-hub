import { api } from './client'
import type { DocumentStatus, Paginated, PartyRole, PartyStatus } from './documents'
import type { ResourceResponse } from './types'

// Контекст подписания по capability-токену: своё участие + агрегированный прогресс (чужих не раскрываем).
export interface SigningContext {
  document: { id: string; title: string; status: DocumentStatus; expires_at: string | null }
  party: {
    name: string
    email: string
    role: PartyRole
    signing_order: number | null
    status: PartyStatus
    signed_at: string | null
  }
  progress: { signed: number; total: number }
  requires_account: boolean
  already_signed: boolean
  expired: boolean
  your_turn: boolean
  can_sign: boolean
}

export interface Signature {
  id: number
  party_id: number
  signature_hash: string
  signed_at: string
}

export interface SigningRequest {
  party_id: number
  signing_order: number | null
  status: PartyStatus
  document: { id: string; title: string; status: DocumentStatus; expires_at: string | null }
}

export async function getSigningContext(token: string): Promise<SigningContext> {
  const { data } = await api.get<ResourceResponse<SigningContext>>(`/signing/${token}`)

  return data.data
}

export async function signWithToken(token: string): Promise<Signature> {
  const { data } = await api.post<ResourceResponse<Signature>>(`/signing/${token}/sign`)

  return data.data
}

export async function listSigningRequests(page = 1): Promise<Paginated<SigningRequest>> {
  const { data } = await api.get<Paginated<SigningRequest>>('/signing-requests', { params: { page } })

  return data
}

export async function signAsIdentity(partyId: number): Promise<Signature> {
  const { data } = await api.post<ResourceResponse<Signature>>(`/signing-requests/${partyId}/sign`)

  return data.data
}
