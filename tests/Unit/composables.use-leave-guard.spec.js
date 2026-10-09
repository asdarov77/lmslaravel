// @vitest-environment jsdom
/**
 * useLeaveGuard: защита несохранённой формы.
 *
 * Проверяем оба выхода: переход внутри SPA (через guard роутера) и
 * закрытие вкладки (beforeunload).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'
import useLeaveGuard from '../../resources/js/composables/useLeaveGuard'

const empty = { render: () => h('div') }

const makeHarness = ({ dirty, confirm } = {}) => {
  const dirtyRef = ref(Boolean(dirty))
  let guard = null
  const FormView = defineComponent({
    setup() {
      guard = useLeaveGuard(() => dirtyRef.value, { confirm })

      return () => h('div')
    },
  })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: empty },
      { path: '/other', component: empty },
      { path: '/form', component: FormView },
    ],
  })
  const Root = defineComponent({ render: () => h(RouterView) })

  return { Root, router, dirtyRef, getGuard: () => guard }
}

const mountAtForm = async (harness) => {
  await harness.router.push('/')
  await harness.router.isReady()
  const wrapper = mount(harness.Root, { global: { plugins: [harness.router] } })
  await harness.router.push('/form')
  await flushPromises()

  return wrapper
}

beforeEach(() => {
  vi.restoreAllMocks()
})

describe('useLeaveGuard: переход внутри SPA', () => {
  it('спрашивает подтверждение только при dirty', async () => {
    const confirm = vi.fn(async () => true)
    const harness = makeHarness({ dirty: false, confirm })
    const wrapper = await mountAtForm(harness)

    await harness.router.push('/other')
    expect(confirm).not.toHaveBeenCalled()
    expect(harness.router.currentRoute.value.path).toBe('/other')

    wrapper.unmount()
  })

  it('отменяет переход, если пользователь отказался', async () => {
    const confirm = vi.fn(async () => false)
    const harness = makeHarness({ dirty: true, confirm })
    const wrapper = await mountAtForm(harness)

    await harness.router.push('/other')

    expect(confirm).toHaveBeenCalledTimes(1)
    expect(harness.router.currentRoute.value.path).toBe('/form')

    wrapper.unmount()
  })

  it('пропускает переход после bypassGuard', async () => {
    const confirm = vi.fn(async () => true)
    const harness = makeHarness({ dirty: true, confirm })
    const wrapper = await mountAtForm(harness)

    harness.getGuard().bypassGuard()
    await harness.router.push('/other')

    expect(confirm).not.toHaveBeenCalled()
    expect(harness.router.currentRoute.value.path).toBe('/other')

    wrapper.unmount()
  })
})

describe('useLeaveGuard: beforeunload', () => {
  it('отменяет закрытие вкладки при несохранённых изменениях', async () => {
    const harness = makeHarness({ dirty: true, confirm: async () => true })
    const wrapper = await mountAtForm(harness)

    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)

    wrapper.unmount()
  })

  it('не мешает закрытию на чистой форме', async () => {
    const harness = makeHarness({ dirty: false, confirm: async () => true })
    const wrapper = await mountAtForm(harness)

    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(false)

    wrapper.unmount()
  })
})
