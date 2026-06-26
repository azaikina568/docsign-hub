import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import LanguageSwitcher from './LanguageSwitcher.vue'
import { i18n, setLocale } from '@/shared/i18n'

afterEach(() => setLocale('en'))

function render() {
  return mount(LanguageSwitcher, { global: { plugins: [i18n] } })
}

describe('LanguageSwitcher', () => {
  it('renders an option per supported locale and reflects the active one', () => {
    const wrapper = render()
    const options = wrapper.findAll('option')

    expect(options.map((o) => o.attributes('value'))).toEqual(['en', 'ru'])
    expect((wrapper.get('select').element as HTMLSelectElement).value).toBe('en')
  })

  it('switches the locale on change', async () => {
    const wrapper = render()

    await wrapper.get('select').setValue('ru')

    expect(i18n.global.locale.value).toBe('ru')
    expect(localStorage.getItem('docsign.locale')).toBe('ru')
  })
})
