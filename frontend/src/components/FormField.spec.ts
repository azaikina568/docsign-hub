import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormField from './FormField.vue'

describe('FormField', () => {
  it('renders the label bound to the input and the current value', () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', modelValue: 'a@b.com' },
    })

    expect(wrapper.get('label').attributes('for')).toBe('email')
    expect(wrapper.get('input').element.value).toBe('a@b.com')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('false')
  })

  it('shows the error and marks the input invalid', () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', modelValue: '', error: 'Required.' },
    })

    expect(wrapper.get('p').text()).toBe('Required.')
    expect(wrapper.get('input').attributes('aria-invalid')).toBe('true')
  })

  it('emits update:modelValue on input', async () => {
    const wrapper = mount(FormField, {
      props: { id: 'email', label: 'Email', modelValue: '' },
    })

    await wrapper.get('input').setValue('typed')

    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['typed'])
  })
})
