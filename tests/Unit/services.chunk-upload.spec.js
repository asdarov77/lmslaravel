// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'

import {
  createTask,
  uploadTask,
  createUploadQueue,
  STATUS,
  DEFAULT_CHUNK_CONCURRENCY,
} from '../../resources/js/services/chunkUpload'

/**
 * Движок загрузки по частям.
 *
 * Проверяется то, что нельзя увидеть на странице глазами:
 *  - собираются ли части в правильном порядке и без потерь;
 *  - учитываются ли уже принятые части (докачка);
 *  - не уезжает ли прогресс за 100% и не прыгает ли назад при
 *    параллельной отправке;
 *  - повторяется ли оборванная часть, и НЕ повторяется ли то, что
 *    сервер отверг по существу.
 */

/** Файл-заглушка: size/slice/lastModified, как у настоящего File. */
const makeFile = (size, { name = 'файл.bin', type = 'application/octet-stream' } = {}) => ({
  name,
  size,
  type,
  lastModified: 1700000000000,
  slice: (start, end) => ({
    start,
    end,
    size: Math.max(0, end - start),
  }),
})

/** Клиент-заглушка: считает вызовы и отдаёт заранее заданные ответы. */
const makeClient = (options = {}) => {
  const {
    totalChunks = 1,
    chunkBytes = 1024,
    received = [],
    failChunks = {},
    failFiles = [],
    failComplete = null,
    initFails = false,
  } = options

  const sent = []
  const inits = []
  const completes = []

  // Имя файла по id загрузки: чтобы «ломается вот этот файл», а не
  // «ломается любая первая часть» — иначе очередь проверяется не там.
  const names = new Map()

  const failFor = (name) => failFiles.includes(name)

  const client = {
    sent,
    inits,
    completes,
    initUpload: vi.fn(async (payload) => {
      inits.push(payload)

      if (initFails) {
        const error = new Error('init failed')
        error.response = { status: 500, data: { error: { message: 'init failed' } } }
        throw error
      }

      // id у каждого файла свой: на сервере это так и есть, а заглушка с
      // одним id на всех смешивала бы имена и «ломала» не тот файл.
      const uploadId = String(inits.length).padStart(2, '0') + 'b'.repeat(38)
      names.set(uploadId, payload.filename)

      return {
        data: {
          data: {
            upload_id: uploadId,
            chunk_bytes: chunkBytes,
            total_chunks: totalChunks,
            // Размер возвращается ТЕМ, что запросили: так ведёт себя
            // сервер (он берёт объявленный размер из init). Заглушка
            // не должна подставлять своё, иначе проверяется не код.
            size: payload.size,
            received,
          },
        },
      }
    }),
    sendChunk: vi.fn(async (uploadId, index, blob, onProgress) => {
      sent.push({ uploadId, index, blob, name: names.get(uploadId) })

      if (onProgress) {
        // Половинный прогресс, потом полный: так ведёт себя реальный
        // сетевой поток, и именно на нём полоса «прыгала».
        onProgress({ loaded: Math.floor(blob.size / 2) })
        onProgress({ loaded: blob.size })
      }

      if (failFor(names.get(uploadId))) {
        const error = new Error('network')
        error.response = { status: 422, data: { error: { message: 'отказ' } } }
        throw error
      }

      if (failChunks[index]) {
        const failure = failChunks[index]
        const remaining = failure.times ?? 1

        if (remaining > 0) {
          failChunks[index] = { ...failure, times: remaining - 1 }
          const error = new Error('network')
          error.response = failure.response
          throw error
        }
      }

      return { data: { data: { received_bytes: blob.size } } }
    }),
    completeUpload: vi.fn(async (uploadId) => {
      completes.push(uploadId)

      if (failComplete) {
        const error = new Error('complete failed')
        error.response = failComplete
        throw error
      }

      return { data: { data: { id: 1, name: 'файл.bin' } } }
    }),
    abortUpload: vi.fn(async () => ({ data: { data: null } })),
  }

  return client
}

/** Ждём, пока условие станет истинным (очередь работает асинхронно). */
const until = async (predicate, attempts = 100) => {
  for (let i = 0; i < attempts; i++) {
    if (predicate()) return true
    await new Promise((resolve) => setTimeout(resolve, 2))
  }
  return false
}

/** Дать микрозадачам выполниться. */
const flush = () => new Promise((resolve) => setTimeout(resolve, 5))

describe('createTask', () => {
  it('задача начинается в очереди с нулевым прогрессом', () => {
    const task = createTask(makeFile(100))

    expect(task.status).toBe(STATUS.QUEUED)
    expect(task.sent).toBe(0)
    expect(task.size).toBe(100)
    expect(task.id).toBeTruthy()
  })

  it('идентификаторы задач не повторяются', () => {
    // Повторяющийся id склеил бы две карточки в одну, и отчёт о
    // прогрессе пришёл бы не тому файлу.
    const ids = Array.from({ length: 50 }, () => createTask(makeFile(1)).id)

    expect(new Set(ids).size).toBe(50)
  })
})

describe('uploadTask: отправка', () => {
  it('файл меньше части уходит одним запросом', async () => {
    const client = makeClient({ totalChunks: 1, chunkBytes: 1024 })
    const task = createTask(makeFile(100))

    await uploadTask(task, { client })

    expect(client.inits).toHaveLength(1)
    expect(client.inits[0]).toMatchObject({ filename: 'файл.bin', size: 100 })
    expect(client.sent.map((c) => c.index)).toEqual([0])
    expect(client.completes).toEqual(['01' + 'b'.repeat(38)])
    expect(task.status).toBe(STATUS.DONE)
    expect(task.sent).toBe(100)
  })

  it('части режутся по границам и отправляются все', async () => {
    const client = makeClient({ totalChunks: 3, chunkBytes: 1024 })
    const task = createTask(makeFile(3 * 1024))

    await uploadTask(task, { client, concurrency: 1 })

    expect(client.sent.map((c) => c.index).sort()).toEqual([0, 1, 2])
    expect(client.sent.map((c) => c.blob.start).sort((a, b) => a - b)).toEqual([0, 1024, 2048])
    expect(client.sent.every((c) => c.blob.size === 1024)).toBe(true)
  })

  it('последняя часть короче остальных', async () => {
    const client = makeClient({ totalChunks: 2, chunkBytes: 1024 })
    const task = createTask(makeFile(1024 + 10))

    await uploadTask(task, { client, concurrency: 1 })

    const sizes = Object.fromEntries(client.sent.map((c) => [c.index, c.blob.size]))

    expect(sizes[0]).toBe(1024)
    expect(sizes[1]).toBe(10)
  })

  it('размер части берётся у сервера, а не из настроек клиента', async () => {
    // Сервер — единственный, кто знает свои ограничения post_max_size.
    // Клиент, приславший свою величину, загрузил бы часть, которую
    // сервер отобьёт.
    const client = makeClient({ totalChunks: 2, chunkBytes: 512 })
    const task = createTask(makeFile(1024))

    await uploadTask(task, { client, chunkBytes: 4 * 1024 * 1024, concurrency: 1 })

    expect(client.sent.every((c) => c.blob.size === 512)).toBe(true)
  })

  it('не более `concurrency` запросов одновременно', async () => {
    let inFlight = 0
    let maxInFlight = 0

    const client = makeClient({ totalChunks: 8, chunkBytes: 1024 })
    client.sendChunk = vi.fn(async (uploadId, index, blob) => {
      inFlight++
      maxInFlight = Math.max(maxInFlight, inFlight)
      await new Promise((resolve) => setTimeout(resolve, 1))
      inFlight--
    })

    const task = createTask(makeFile(8 * 1024))
    await uploadTask(task, { client, concurrency: 3 })

    expect(client.sendChunk).toHaveBeenCalledTimes(8)
    expect(maxInFlight).toBeLessThanOrEqual(3)
    expect(maxInFlight).toBeGreaterThan(1)
  })

  it('по умолчанию части идут параллельно', async () => {
    expect(DEFAULT_CHUNK_CONCURRENCY).toBeGreaterThan(1)
  })
})

describe('uploadTask: докачка', () => {
  it('уже принятые части не отправляются повторно', async () => {
    const client = makeClient({ totalChunks: 3, chunkBytes: 1024, received: [0, 1] })
    const task = createTask(makeFile(3 * 1024))

    await uploadTask(task, { client, concurrency: 1 })

    expect(client.sent.map((c) => c.index)).toEqual([2])
    expect(client.completes).toHaveLength(1)
  })

  it('прогресс начинается с уже отправленного, а не с нуля', async () => {
    const client = makeClient({ totalChunks: 3, chunkBytes: 1024, received: [0, 1] })
    const task = createTask(makeFile(3 * 1024))
    const seen = []

    await uploadTask(task, {
      client,
      concurrency: 1,
      onChange: (t) => seen.push(t.sent),
    })

    // Первое событие — «началась загрузка» (до ответа сервера ноль),
    // второе — уже принятые сервером 2 части из 3.
    expect(seen[0]).toBe(0)
    expect(seen[1]).toBe(2 * 1024)
  })

  it('полностью принятая загрузка только завершается', async () => {
    const client = makeClient({ totalChunks: 2, chunkBytes: 1024, received: [0, 1] })
    const task = createTask(makeFile(2 * 1024))

    await uploadTask(task, { client })

    expect(client.sent).toHaveLength(0)
    expect(task.status).toBe(STATUS.DONE)
  })
})

describe('uploadTask: обрывы и повторы', () => {
  it('оборванная часть повторяется', async () => {
    const client = makeClient({
      totalChunks: 2,
      chunkBytes: 1024,
      failChunks: { 1: { times: 1 } },
    })

    const task = createTask(makeFile(2 * 1024))
    await uploadTask(task, { client, concurrency: 1 })

    // Первая попытка + повтор.
    expect(client.sent.filter((c) => c.index === 1)).toHaveLength(2)
    expect(task.status).toBe(STATUS.DONE)
  })

  it('отказ сервера по существу не повторяется', async () => {
    // 422 — сервер сказал «часть не того размера». Повтор вернёт то же
    // самое, и пользователь будет смотреть на крутящийся индикатор.
    const client = makeClient({
      totalChunks: 2,
      chunkBytes: 1024,
      failChunks: { 1: { times: 99, response: { status: 422, data: {} } } },
    })

    const task = createTask(makeFile(2 * 1024))

    await expect(uploadTask(task, { client, concurrency: 1 })).rejects.toBeTruthy()
    expect(client.sent.filter((c) => c.index === 1)).toHaveLength(1)
    expect(task.status).toBe(STATUS.ERROR)
  })

  it('после исчерпания повторов задача помечается ошибкой с текстом сервера', async () => {
    const client = makeClient({ initFails: true })
    const task = createTask(makeFile(10))

    await expect(uploadTask(task, { client })).rejects.toBeTruthy()

    expect(task.status).toBe(STATUS.ERROR)
    expect(task.error).toBe('init failed')
  })

  it('сбой на complete не считается загруженным файлом', async () => {
    const client = makeClient({
      totalChunks: 1,
      chunkBytes: 1024,
      failComplete: { status: 500, data: {} },
    })

    const task = createTask(makeFile(100))

    await expect(uploadTask(task, { client })).rejects.toBeTruthy()
    expect(task.status).toBe(STATUS.ERROR)
    expect(task.result).toBeNull()
  })
})

describe('uploadTask: прогресс', () => {
  it('не выходит за размер файла', async () => {
    const client = makeClient({ totalChunks: 4, chunkBytes: 1024 })
    const seen = []

    await uploadTask(createTask(makeFile(4 * 1024)), {
      client,
      concurrency: 4,
      onChange: (t) => seen.push(t.sent),
    })

    expect(Math.max(...seen)).toBe(4 * 1024)
  })

  it('повтор одной части не удваивает прогресс', async () => {
    // Ключевое свойство, ради которого отправленные байты считаются
    // ПО ЧАСТЯМ, а не общим счётчиком: часть ушла целиком, затем её
    // повторили. Наивное сложение событий дало бы 1024 + 1024 + 1024 и
    // полосу уехала бы за размер файла.
    const client = makeClient({ totalChunks: 2, chunkBytes: 1024 })
    const seen = []

    let attempt = 0

    client.sendChunk = vi.fn(async (uploadId, index, blob, onProgress) => {
      onProgress({ loaded: blob.size })

      // Первая попытка второй части обрывается после полного отчёта.
      if (index === 1 && attempt++ === 0) {
        const error = new Error('network')
        throw error
      }

      return { data: { data: {} } }
    })

    const task = createTask(makeFile(2 * 1024))
    await uploadTask(task, { client, concurrency: 1, onChange: (t) => seen.push(t.sent) })

    expect(Math.max(...seen)).toBeLessThanOrEqual(2 * 1024)
    expect(task.status).toBe(STATUS.DONE)
  })

  it('пустой файл завершается без единой части', async () => {
    const client = makeClient({ totalChunks: 0, chunkBytes: 1024 })
    const task = createTask(makeFile(0))

    await uploadTask(task, { client })

    expect(client.sent).toHaveLength(0)
    expect(client.completes).toHaveLength(1)
    expect(task.status).toBe(STATUS.DONE)
  })
})

describe('createUploadQueue', () => {
  // Клиент подставляется в очередь явно: иначе она пошла бы в сеть, и
  // тест проверял бы не очередь, а доступность сервера.
  const queueWith = (options = {}) => {
    const client = makeClient({ totalChunks: 1, chunkBytes: 1024, ...options })
    const queue = createUploadQueue({ client })

    return { queue, client }
  }

  it('add кладёт файл в очередь и отдаёт задачу', () => {
    const { queue } = queueWith()
    const task = queue.add(makeFile(10))

    expect(queue.tasks).toHaveLength(1)
    expect(queue.tasks[0]).toBe(task)
  })

  it('addAll добавляет несколько файлов разом', () => {
    const { queue } = queueWith()

    queue.addAll([makeFile(1), makeFile(2), makeFile(3)])

    expect(queue.tasks).toHaveLength(3)
  })

  it('addAll игнорирует то, что не является файлом', () => {
    const { queue } = queueWith()

    queue.addAll([null, undefined, { name: 'не файл' }, makeFile(5)])

    expect(queue.tasks).toHaveLength(1)
    expect(queue.tasks[0].size).toBe(5)
  })

  it('remove убирает задачу из списка', async () => {
    const { queue } = queueWith()
    const task = queue.add(makeFile(10))

    queue.remove(task)
    await flush()

    expect(queue.tasks).toHaveLength(0)
    // Задача успела начаться, поэтому отмена не переводит её в
    // CANCELLED: запрос уже ушёл, и статус «отменена» был бы враньём.
    // Отмена запрещает ДОСЫЛК частей и сборку, а не переписывает
    // состояние уже идущего запроса.
    expect([STATUS.CANCELLED, STATUS.DONE, STATUS.UPLOADING]).toContain(task.status)
  })

  it('remove отменяет загрузку на сервере, если она уже началась', async () => {
    const client = makeClient({ totalChunks: 1, chunkBytes: 1024 })
    const abortUpload = vi.fn(async () => ({ data: { data: null } }))
    client.abortUpload = abortUpload

    const queue = createUploadQueue({ client })
    const task = queue.add(makeFile(10))

    // Сервер уже начал приём: id загрузки известен.
    await until(() => Boolean(task.uploadId))
    const uploadId = task.uploadId
    queue.remove(task)
    await flush()

    expect(abortUpload).toHaveBeenCalledWith(uploadId)
  })

  it('очередь не поднимает больше файлов, чем fileConcurrency', async () => {
    let inFlight = 0
    let maxInFlight = 0

    const client = makeClient({ totalChunks: 1, chunkBytes: 1024 })
    client.sendChunk = vi.fn(async () => {
      inFlight++
      maxInFlight = Math.max(maxInFlight, inFlight)
      await new Promise((r) => setTimeout(r, 5))
      inFlight--
      return { data: { data: {} } }
    })

    const queue = createUploadQueue({ client, fileConcurrency: 2 })
    queue.addAll(Array.from({ length: 6 }, () => makeFile(10)))

    await until(() => queue.tasks.every((t) => t.status === STATUS.DONE))
    expect(maxInFlight).toBeLessThanOrEqual(2)
  })

  it('ошибка одного файла не срывает очередь', async () => {
    const client = makeClient({
      totalChunks: 1,
      chunkBytes: 1024,
      failFiles: ['плохой.bin'],
    })

    const queue = createUploadQueue({ client })
    queue.addAll([makeFile(10, { name: 'плохой.bin' }), makeFile(10, { name: 'хороший.bin' })])

    await until(() => queue.tasks.every((t) => t.status !== STATUS.UPLOADING))

    const failed = queue.tasks.find((t) => t.name === 'плохой.bin')
    const ok = queue.tasks.find((t) => t.name === 'хороший.bin')

    expect(failed.status).toBe(STATUS.ERROR)
    expect(ok.status).toBe(STATUS.DONE)
  })

  it('clearFinished убирает завершённые и ошибочные, но не активные', () => {
    // Клиент подставлен, иначе очередь ушла бы в сеть и напечатала
    // «Network error» — шум в выводе, который маскирует настоящие ошибки.
    const { queue } = queueWith()

    const done = queue.add(makeFile(1))
    done.status = STATUS.DONE
    const failed = queue.add(makeFile(2))
    failed.status = STATUS.ERROR
    const active = queue.add(makeFile(3))
    active.status = STATUS.UPLOADING

    queue.clearFinished()

    expect(queue.tasks).toHaveLength(1)
    expect(queue.tasks[0]).toBe(active)
  })
})
