import { z } from 'zod'

// Клиентские схемы зеркалят серверные правила (dev-минимум Password::defaults и Form Requests),
// чтобы ловить очевидные ошибки до запроса. Сервер остаётся источником истины — его 422 всё равно показываем.

export const passwordRules = ['At least 8 characters', 'At least one letter and one number']

export const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters.')
  .regex(/[a-zA-Z]/, 'Password must contain at least one letter.')
  .regex(/\d/, 'Password must contain at least one number.')

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, 'Name is required.').max(255, 'Name is too long.'),
    email: z.email('Enter a valid email address.').max(255, 'Email is too long.'),
    password: passwordSchema,
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match.',
  })

export const loginSchema = z.object({
  email: z.email('Enter a valid email address.'),
  password: z.string().min(1, 'Password is required.'),
})

export const createDocumentSchema = z.object({
  title: z.string().trim().min(1, 'Title is required.').max(255, 'Title is too long.'),
})

export const partySchema = z.object({
  name: z.string().trim().min(1, 'Name is required.').max(255, 'Name is too long.'),
  email: z.email('Enter a valid email address.').max(255, 'Email is too long.'),
})

// Прогоняет данные через схему и возвращает первое сообщение на поле, либо null если всё валидно.
export function validate<T>(schema: z.ZodType<T>, data: unknown): Record<string, string> | null {
  const result = schema.safeParse(data)

  if (result.success) {
    return null
  }

  const fields: Record<string, string> = {}

  for (const issue of result.error.issues) {
    const key = issue.path[0]

    if (typeof key === 'string' && !(key in fields)) {
      fields[key] = issue.message
    }
  }

  return fields
}
