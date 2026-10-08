// @vitest-environment jsdom
import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { reactive, nextTick } from 'vue'

import { createUploadQueue, uploadTask, STATUS } from '../../resources/js/services/chunkUpload'
import { extractFieldErrors, unwrapResponse } from '../../resources/js/api/envelope'
import { filenameFromDisposition } from '../../resources/js/api/filemanager.api'

/**
 * Регрессии, которые не видны на странице, пока не поздно.
 *
 * Главная из них — РЕАКТИВНОСТЬ СПИСКА ЗАГРУЗКИ. Реактивность Vue 3
 * устроена через прокси: уведомление приходит только при записи через
 * прокси. Если движок держал свой собственный массив задач, он писал
 * бы в «сырые» объекты, а шаблон читал бы reactive-версию — и не увидел
 * бы ничего. Симптом обманчив: запросы уходят, файлы появляются на
 * диске и в базе, а полос прогресса нет. Именно так и выглядело
 * первое открытие файлового менеджера.
 */

/** Клиент-заглушка загрузки: всё принимает. */
const makeClient = () => {
  const file = {
    name: 'файл.bin',
    size: 100,
    type: 'application/octet-stream',
    lastModified: 1700000000000,
    slice: (start, end) => ({ start, end, size: Math.max(0, end - start) }),
  }

  return {
    file,
    initUpload: vi.fn(async () => ({
      data: { data: { upload_id: 'c'.repeat(40), chunk_bytes: 1024, total_chunks: 1, size: 100, received: [] } },
    })),
    sendChunk: vi.fn(async (_id, _index, blob) => ({ data: { data: { received_bytes: blob.size } } })),
    completeUpload: vi.fn(async () => ({ data: { data: { id: 7, name: 'файл.bin' } } })),
    abortUpload: vi.fn(async () => ({ data: { data: null } })),
  }
}

/**
 * Мини-страница: список задач принадлежит компоненту, очередь работает
 * с ним. Ровно так устроено настоящее состояние.
 */
const makePage = (queueOptions = {}) => ({
  components: {},
  data() {
    return { uploadTasks: [] }
  },
  created() {
    this.queue = createUploadQueue({ tasks: this.uploadTasks, ...queueOptions })
  },
  template: `<div>
    <span data-test="count">{{ uploadTasks.length }}</span>
    <span data-test="status">{{ uploadTasks[0] ? uploadTasks[0].status : '-' }}</span>
    <span data-test="sent">{{ uploadTasks[0] ? uploadTasks[0].sent : '-' }}</span>
  </div>`,
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('список загрузок реактивен', () => {
  it('страница видит добавленную задачу', async () => {
    const client = makeClient()
    const wrapper = mount(makePage({ client }))

    expect(wrapper.get('[data-test="count"]').text()).toBe('0')

    wrapper.vm.queue.addAll([client.file])
    await nextTick()

    // Регресс: было 0 навсегда, хотя файл загружался.
    expect(wrapper.get('[data-test="count"]').text()).toBe('1')
  })

  it('страница видит смену статуса и прогресс', async () => {
    const client = makeClient()

    // Часть отправляется не мгновенно — иначе проверить нечего. Отчёт
    // о половине и освобождение разведены двумя промисами, чтобы тест
    // не зависел от того, сколько микрозадач успело выполниться.
    let release
    let halfReported
    const half = new Promise((resolve) => {
      halfReported = resolve
    })

    client.sendChunk = vi.fn(async (_id, _index, blob, onProgress) => {
      onProgress({ loaded: Math.floor(blob.size / 2) })
      halfReported()
      await new Promise((resolve) => {
        release = resolve
      })
      onProgress({ loaded: blob.size })
      return { data: { data: {} } }
    })

    const wrapper = mount(makePage({ client }))
    wrapper.vm.queue.addAll([client.file])

    await half
    await nextTick()

    expect(wrapper.get('[data-test="status"]').text()).toBe(STATUS.UPLOADING)
    expect(Number(wrapper.get('[data-test="sent"]').text())).toBe(50)

    release()
    await new Promise((resolve) => setTimeout(resolve, 5))
    await nextTick()

    expect(wrapper.get('[data-test="status"]').text()).toBe(STATUS.DONE)
    expect(Number(wrapper.get('[data-test="sent"]').text())).toBe(100)
  })

  it('remove убирает задачу из списка страницы', async () => {
    const client = makeClient()
    const wrapper = mount(makePage({ client }))

    const task = wrapper.vm.queue.addAll([client.file])[0]
    await nextTick()
    expect(wrapper.get('[data-test="count"]').text()).toBe('1')

    wrapper.vm.queue.remove(task)
    await nextTick()

    expect(wrapper.get('[data-test="count"]').text()).toBe('0')
  })

  it('очередь работает и с обычным массивом (тесты, не-Vue код)', async () => {
    const client = makeClient()
    const tasks = []
    const queue = createUploadQueue({ tasks, client })

    queue.addAll([client.file])
    await new Promise((resolve) => setTimeout(resolve, 5))

    expect(tasks).toHaveLength(1)
    expect(tasks[0].status).toBe(STATUS.DONE)
  })

  it('uploadTask переносимо: работает без очереди и без Vue', async () => {
    const client = makeClient()
    const task = { id: 'x', file: client.file, size: 100, sent: 0, status: STATUS.QUEUED, error: null, uploadId: null, result: null }

    await uploadTask(task, { client })

    expect(task.status).toBe(STATUS.DONE)
    expect(task.result).toEqual({ id: 7, name: 'файл.bin' })
  })
})

describe('разбор ответа', () => {
  it('unwrapResponse достаёт payload из конверта', () => {
    expect(unwrapResponse({ data: { success: true, data: { id: 1 }, error: null, meta: null } })).toEqual({ id: 1 })
    expect(unwrapResponse({ data: { success: true, data: [], error: null, meta: null } })).toEqual([])
  })

  it('ошибка поля name читается из конверта', () => {
    // Сервер кладёт ошибки в error.details, а не на верхний уровень.
    // Без этого страница показывала бы «Не удалось сохранить изменения»
    // вместо объяснения, что именно не так с именем.
    const error = {
      response: {
        status: 422,
        data: {
          success: false,
          data: { message: 'Имя папки: запрещены символы «/»…', errors: { name: ['Имя папки: запрещены символы «/»…'] } },
          error: { code: '422', message: 'Имя папки: запрещены символы «/»…', details: { name: ['Имя папки: запрещены символы «/»…'] } },
          meta: null,
        },
      },
    }

    const { fields, general } = extractFieldErrors(error, 'Не удалось сохранить изменения')

    expect(fields.name).toContain('запрещены символы')
    expect(general).toContain('запрещены символы')
  })

  it('успешный ответ без errors даёт общий текст', () => {
    const { fields, general } = extractFieldErrors({ response: { status: 500, data: {} } }, 'Упало')

    expect(fields).toEqual({})
    expect(general).toBe('Упало')
  })
})

describe('имя файла из Content-Disposition', () => {
  it('берётся из filename* (кириллица не искажается)', () => {
    // Без RFC 5987 кириллическое имя превращается в «report.txt», и
    // пользователь сохранял файл с чужим расширением и без следа, что
    // это был его файл.
    const header = "attachment; filename=report.txt; filename*=UTF-8''%D0%9E%D1%82%D1%87%D1%91%D1%82.txt"

    expect(filenameFromDisposition(header)).toBe('Отчёт.txt')
  })

  it('при отсутствии filename* берётся обычный filename', () => {
    expect(filenameFromDisposition('attachment; filename="report.txt"')).toBe('report.txt')
  })

  it('при отсутствии заголовка не выдумывается имя', () => {
    expect(filenameFromDisposition(undefined)).toBe('')
    expect(filenameFromDisposition('')).toBe('')
  })
})
