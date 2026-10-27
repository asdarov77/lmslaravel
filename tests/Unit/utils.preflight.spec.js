// @vitest-environment jsdom
/**
 * Preflight раздачи приватного контента.
 *
 * Проверяем главное: вердикт строится по тому, КТО обработал внутренний
 * location. Путать эти случаи опасно — режим nginx при PHP-обработке
 * отдаёт материалы пустыми (200 без тела, без ошибок в логах).
 */
import { describe, it, expect, vi } from 'vitest'
import { probeDelivery, nginxSnippet } from '../../resources/js/utils/preflight'

/** Ответ fetch-заглушки: status + заголовки. */
const response = (status, headers = {}) => ({
  status,
  headers: {
    forEach: (cb) => Object.entries(headers).forEach(([key, value]) => cb(value, key)),
  },
})

describe('utils/preflight: вердикт', () => {
  it('nginx перед приложением — проверка пройдена', async () => {
    const fetchImpl = vi.fn(async () => response(404, { server: 'nginx/1.24.0' }))

    const result = await probeDelivery('/_protected-content', 'http://app.test', fetchImpl)

    expect(result.ok).toBe(true)
    expect(result.isNginx).toBe(true)
    expect(result.verdict).toBe('nginx-in-front')
    expect(fetchImpl).toHaveBeenCalledWith(
      'http://app.test/_protected-content/__preflight__',
      expect.objectContaining({ credentials: 'same-origin' })
    )
  })

  it('ответ PHP без заголовка Server — переключать в nginx бессмысленно', async () => {
    const fetchImpl = vi.fn(async () => response(404))

    const result = await probeDelivery('/_protected-content', 'http://app.test', fetchImpl)

    expect(result.ok).toBe(false)
    expect(result.verdict).toBe('php-direct')
    expect(result.detail).toMatch(/пустыми/)
  })

  it('чужой веб-сервер называется прямо', async () => {
    const fetchImpl = vi.fn(async () => response(404, { server: 'Apache/2.4' }))

    const result = await probeDelivery('/_protected-content', 'http://app.test', fetchImpl)

    expect(result.ok).toBe(false)
    expect(result.verdict).toBe('other-server')
    expect(result.detail).toMatch(/Apache/)
  })

  it('недоступный сервер не роняет проверку', async () => {
    const fetchImpl = vi.fn(async () => {
      throw new Error('network down')
    })

    const result = await probeDelivery('/_protected-content', 'http://app.test', fetchImpl)

    expect(result.verdict).toBe('unreachable')
    expect(result.ok).toBe(false)
  })

  it('путь без ведущего слэша приводится к корректному URL', async () => {
    const fetchImpl = vi.fn(async () => response(404, { server: 'nginx' }))

    await probeDelivery('_protected-content', 'http://app.test/', fetchImpl)

    expect(fetchImpl.mock.calls[0][0]).toBe('http://app.test/_protected-content/__preflight__')
  })
})

describe('utils/preflight: сниппет конфига', () => {
  it('подставляет internal-location из настроек', () => {
    const snippet = nginxSnippet('/_protected-content')

    expect(snippet).toContain('location /_protected-content {')
    expect(snippet).toContain('internal;')
  })

  it('без значения даёт безопасный путь по умолчанию', () => {
    expect(nginxSnippet('')).toContain('/_protected-content')
  })
})
