import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ToastHost from './ToastHost.vue'
import { toast, useToasts } from '@/shared/toast'
import { i18n } from '@/shared/i18n'

const { toasts } = useToasts()

// TransitionGroup стабаем плоским контейнером: в jsdom анимации нет, нужен детерминированный рендер списка.
function render() {
  return mount(ToastHost, {
    global: { plugins: [i18n], stubs: { TransitionGroup: { template: '<div><slot /></div>' } } },
  })
}

beforeEach(() => {
  vi.useFakeTimers()
  toasts.splice(0)
})

afterEach(() => {
  vi.useRealTimers()
})

describe('ToastHost', () => {
  it('renders messages with the right aria role per variant', () => {
    toast.success('Done.')
    toast.error('Failed.')

    const wrapper = render()
    const items = wrapper.findAll('[role]')

    expect(wrapper.text()).toContain('Done.')
    expect(wrapper.text()).toContain('Failed.')
    expect(items.find((n) => n.text().includes('Done.'))?.attributes('role')).toBe('status')
    expect(items.find((n) => n.text().includes('Failed.'))?.attributes('role')).toBe('alert')
  })

  it('removes a toast when its dismiss button is clicked', async () => {
    toast.info('Heads up.')
    const wrapper = render()

    await wrapper.get('button[aria-label="Dismiss"]').trigger('click')

    expect(wrapper.text()).not.toContain('Heads up.')
    expect(toasts).toHaveLength(0)
  })
})
