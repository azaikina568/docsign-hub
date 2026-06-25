import { api } from './client'
import type { AuthResponse, ResourceResponse, User } from './types'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface LoginPayload {
  email: string
  password: string
}

export async function register(payload: RegisterPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/register', payload)

  return data
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/login', payload)

  return data
}

export async function me(): Promise<User> {
  const { data } = await api.get<ResourceResponse<User>>('/me')

  return data.data
}

// Logout отзывает всю семью токенов по текущему access (refresh гасится вместе с семьёй).
export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}

// Подтверждение email: query (signature/expires из письма) переносим в URL как есть — подпись API-роута.
export async function verifyEmail(id: string, hash: string, signedQuery: string): Promise<{ message: string }> {
  const { data } = await api.get<{ message: string }>(`/auth/verify/${id}/${hash}?${signedQuery}`)

  return data
}

export async function resendVerification(): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/auth/verification/resend')

  return data
}
