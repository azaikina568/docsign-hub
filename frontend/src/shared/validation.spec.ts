import { describe, expect, it } from 'vitest'
import { createDocumentSchema, loginSchema, partySchema, registerSchema, validate } from './validation'

describe('validate', () => {
  it('returns null when data is valid', () => {
    expect(validate(loginSchema, { email: 'a@b.com', password: 'secret' })).toBeNull()
  })

  it('maps the first issue per field', () => {
    const errors = validate(loginSchema, { email: 'not-an-email', password: '' })

    expect(errors).toEqual({
      email: 'Enter a valid email address.',
      password: 'Password is required.',
    })
  })
})

describe('registerSchema', () => {
  const base = { name: 'Jane', email: 'jane@example.com', password: 'secret12', password_confirmation: 'secret12' }

  it('accepts a strong, matching password', () => {
    expect(validate(registerSchema, base)).toBeNull()
  })

  it('rejects a password without a digit', () => {
    const errors = validate(registerSchema, { ...base, password: 'password', password_confirmation: 'password' })

    expect(errors?.password).toBe('Password must contain at least one number.')
  })

  it('rejects a too-short password', () => {
    const errors = validate(registerSchema, { ...base, password: 'ab1', password_confirmation: 'ab1' })

    expect(errors?.password).toBe('Password must be at least 8 characters.')
  })

  it('flags a mismatched confirmation on the confirmation field', () => {
    const errors = validate(registerSchema, { ...base, password_confirmation: 'secret99' })

    expect(errors).toEqual({ password_confirmation: 'Passwords do not match.' })
  })
})

describe('document schemas', () => {
  it('requires a non-empty title', () => {
    expect(validate(createDocumentSchema, { title: '   ' })?.title).toBe('Title is required.')
  })

  it('validates the party email', () => {
    expect(validate(partySchema, { name: 'Bob', email: 'bob' })?.email).toBe('Enter a valid email address.')
  })
})
