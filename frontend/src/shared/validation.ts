import { z } from 'zod'
import { t } from '@/shared/i18n'

// Клиентские схемы зеркалят серверные правила (dev-минимум Password::defaults и Form Requests),
// чтобы ловить очевидные ошибки до запроса. Сервер остаётся источником истины — его 422 всё равно показываем.
//
// В сообщениях храним i18n-ключи, а не текст: схемы создаются один раз, а локаль может меняться —
// перевод применяется в validate() в момент проверки (под активный язык).

export const passwordRules = ['validation.passwordRuleLength', 'validation.passwordRuleComplexity']

export const passwordSchema = z
  .string()
  .min(8, 'validation.passwordMin')
  .regex(/[a-zA-Z]/, 'validation.passwordLetter')
  .regex(/\d/, 'validation.passwordNumber')

export const registerSchema = z
  .object({
    name: z.string().trim().min(1, 'validation.nameRequired').max(255, 'validation.nameTooLong'),
    email: z.email('validation.email').max(255, 'validation.emailTooLong'),
    password: passwordSchema,
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    path: ['password_confirmation'],
    message: 'validation.passwordsMismatch',
  })

export const loginSchema = z.object({
  email: z.email('validation.email'),
  password: z.string().min(1, 'validation.passwordRequired'),
})

export const createDocumentSchema = z.object({
  title: z.string().trim().min(1, 'validation.titleRequired').max(255, 'validation.titleTooLong'),
})

export const partySchema = z.object({
  name: z.string().trim().min(1, 'validation.nameRequired').max(255, 'validation.nameTooLong'),
  email: z.email('validation.email').max(255, 'validation.emailTooLong'),
})

// Прогоняет данные через схему и возвращает первое (переведённое) сообщение на поле, либо null если всё валидно.
export function validate<T>(schema: z.ZodType<T>, data: unknown): Record<string, string> | null {
  const result = schema.safeParse(data)

  if (result.success) {
    return null
  }

  const fields: Record<string, string> = {}

  for (const issue of result.error.issues) {
    const key = issue.path[0]

    if (typeof key === 'string' && !(key in fields)) {
      fields[key] = t(issue.message)
    }
  }

  return fields
}
