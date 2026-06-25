// Формы ответов API (зеркалят бэкендовые Resource'ы). Держим здесь, чтобы стор и фичи делили одни типы.

export interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  created_at: string | null
}

export interface TokenPair {
  token_type: 'Bearer'
  access_token: string
  access_expires_at: string
  refresh_token: string
  refresh_expires_at: string
}

export interface AuthResponse {
  user: User
  tokens: TokenPair
}

// Единый конверт ресурса Laravel (`{ data: ... }`).
export interface ResourceResponse<T> {
  data: T
}

// Тело ошибки Laravel: message + (для 422) ошибки валидации по полям.
export interface ApiErrorBody {
  message: string
  errors?: Record<string, string[]>
}
