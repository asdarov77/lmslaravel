import { initUpload, sendChunk, completeUpload, abortUpload } from '../api/filemanager.api'

/**
 * Загрузка больших файлов по частям: движок очереди.
 *
 * Почему отдельный движок, а не axios-вызов в компоненте:
 *
 *  1. ПРОГРЕСС ПО КАЖДОМУ ФАЙЛУ. Формировать его в компоненте нечего
 *     было бы, если не держать здесь состояние загрузки: список файлов
 *     в очереди, сколько байт принято, что уже в фоне.
 *
 *  2. ДОКАЧКА. Файл режется на части, отправляется по нескольку
 *     одновременно, при обрыве повторяется. При обрыве СЕТИ (а не
 *     страницы) повтор даёт нужный кусок, а «начать заново» означало
 *     бы, что файл на несколько гигабайт не загрузится никогда.
 *
 *  3. ПАРАЛЛЕЛИЗМ ОГРАНИЧЕН. Десять файлов по пять частей — это 50
 *     одновременных запросов, и они упрутся либо в лимит соединений
 *     браузера (6 на хост), либо в worker'ы PHP. Очередь с пределом
 *     держит нагрузку предсказуемой.
 *
 *  4. НУЛЕВАЯ ЗАВИСИМОТЬ ОТ VUE. Модуль принимает клиент аргументом и
 *     возвращает состояние через onChange. Из-за этого он проверяется
 *     обычным vitest без поднятия компонента, а страница остаётся
 *     тонкой.
 *
 * Что НЕ делает модуль: не ходит в Vuex и не знает про страницы. Состояние
 * живёт здесь и отдаётся наружу колбэком.
 */

/** Сколько частей одного файла уходит одновременно. */
export const DEFAULT_CHUNK_CONCURRENCY = 3

/** Сколько файлов загружается одновременно. */
export const DEFAULT_FILE_CONCURRENCY = 2

/** Сколько раз повторяется одна часть при обрыве. */
export const DEFAULT_RETRIES = 2

/** Статусы задачи. */
export const STATUS = {
  QUEUED: 'queued',
  UPLOADING: 'uploading',
  DONE: 'done',
  ERROR: 'error',
  CANCELLED: 'cancelled',
}

/**
 * Создаёт задачу на файл.
 *
 * Идентификатор задачи — наш собственный, а не индекс в массиве:
 * индексы сдвигаются при удалении из очереди, и отчёт о прогрессе
 * пришёл бы не тому файлу.
 */
export const createTask = (file, { folderId = null } = {}) => ({
  id: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`,
  file,
  name: file.name,
  size: typeof file.size === 'number' ? file.size : 0,
  folderId,
  sent: 0,
  status: STATUS.QUEUED,
  error: null,
  uploadId: null,
  result: null,
})

/** Готов ли файл к отправке (в браузере у File всегда есть размер). */
const isUsableFile = (file) => Boolean(file) && typeof file.slice === 'function' && file.size >= 0

/**
 * Отправляет части параллельно, по `concurrency` штук.
 *
 * Список заданий заранее свёрстан в обратном порядке и с конца: так
 * части досылаются с конца файла, и при обрыве выигрывает хвост, а не
 * начало. Для обычного файла это безразлично, а вот для недописанного
 * видео или многотомного архива порядок важен.
 *
 * @param {object} api клиент загрузки (подменяется в тестах)
 * @param {Array<{index: number, blob: Blob, uploadId: string}>} jobs
 * @param {number} concurrency
 * @param {number} retries
 * @param {(job: object, loaded: number) => void} onProgress
 */
const runChunks = async (api, jobs, concurrency, retries, onProgress) => {
  const queue = jobs.slice().reverse()

  const worker = async () => {
    while (queue.length > 0) {
      const job = queue.pop()

      if (!job) return

      await withRetry(
        () =>
          // Клиент берётся из аргумента, а не из импорта: иначе
          // подставленный клиент использовался бы для init, а части
          // уходили бы в настоящую сеть. Выглядело бы это как «в тестах
          // всё хорошо, а на живой странице загрузка не идёт».
          api.sendChunk(job.uploadId, job.index, job.blob, (event) => {
            // Прогресс внутри части нужен, чтобы полоса не «замирала»
            // на одном куске на медленной сети.
            if (event.loaded !== undefined) {
              onProgress(job, event.loaded)
            }
          }),
        retries
      )
    }
  }

  const count = Math.min(concurrency, Math.max(1, queue.length))
  const workers = []

  for (let i = 0; i < count; i++) {
    workers.push(worker())
  }

  await Promise.all(workers)
}

/**
 * Повторяет операцию при обрыве сети.
 *
 * Повторяются только сетевые ошибки и 5xx. 422 (часть не того размера)
 * и 4xx повторять бессмысленно: сервер ответит то же самое, и
 * пользователь будет смотреть на крутящийся индикатор.
 */
const withRetry = async (fn, retries) => {
  let attempt = 0

  for (;;) {
    try {
      return await fn()
    } catch (error) {
      attempt++

      if (attempt > retries || !isRetriable(error)) {
        throw error
      }
    }
  }
}

/** Сетевой обрыв или ответ сервера, который имеет смысл повторить. */
const isRetriable = (error) => {
  const status = error?.response?.status

  if (status === undefined || status === null) {
    // Нет ответа — обрыв, отказ или CORS. Повтор уместен.
    return true
  }

  return status >= 500 && status !== 501
}

/**
 * Загружает один файл целиком.
 *
 * @param {object} task          задача из createTask
 * @param {object} options
 * @param {object} options.client    http-клиент (по умолчанию api-слой)
 * @param {number} options.chunkBytes размер части, если сервер его не прислал
 * @param {(task: object) => void} options.onChange вызывается после каждого изменения
 * @returns {Promise<object>} задача в статусе DONE
 */
export const uploadTask = async (task, options = {}) => {
  const {
    client = null,
    chunkBytes = null,
    concurrency = DEFAULT_CHUNK_CONCURRENCY,
    retries = DEFAULT_RETRIES,
    onChange = () => {},
  } = options

  // Клиент можно подменить (тесты) — но реально используется тот же
  // api-слой, что и в остальном приложении: он один знает про токен.
  const api = client ?? { initUpload, sendChunk, completeUpload, abortUpload }

  if (!isUsableFile(task.file)) {
    task.status = STATUS.ERROR
    task.error = 'Файл недоступен'
    onChange(task)
    throw new Error(task.error)
  }

  task.status = STATUS.UPLOADING
  task.error = null
  onChange(task)

  let uploadId = null

  try {
    // init идемпотентен: повтор с теми же метаданными возвращает ту же
    // загрузку и список принятых частей. Это и есть докачка после
    // перезагрузки страницы — клиенту не нужно ничего хранить.
    const initResponse = await api.initUpload({
      filename: task.name,
      size: task.size,
      mime: task.file.type || null,
      last_modified: task.file.lastModified || null,
      folder_id: task.folderId,
    })

    const init = initResponse?.data?.data ?? {}
    uploadId = init.upload_id
    task.uploadId = uploadId

    const size = typeof init.size === 'number' ? init.size : task.size
    const partSize = init.chunk_bytes || chunkBytes || 4 * 1024 * 1024
    const total = typeof init.total_chunks === 'number' ? init.total_chunks : Math.ceil(size / partSize)
    const received = new Set(Array.isArray(init.received) ? init.received : [])

    // Уже принятые части считаются отправленными, иначе после докачки
    // полоса прыгала бы с 0 процентов и пользователь решил бы, что
    // загрузка началась заново.
    const alreadySent = Math.min(
      size,
      Array.from(received).reduce(
        (sum, index) => sum + chunkLength(task.file, index, partSize, size),
        0
      )
    )

    task.sent = alreadySent
    onChange(task)

    const jobs = []

    for (let index = 0; index < total; index++) {
      if (received.has(index)) continue

      const start = index * partSize
      const blob = task.file.slice(start, Math.min(start + partSize, size))

      jobs.push({ index, blob, uploadId })
    }

    if (jobs.length > 0) {
      /*
       * Отправленные байты считаются как «уже принятые сервером» плюс
       * «сколько на данный момент передано по каждой части в полёте».
       *
       * Суммировать по частям в map, а не в одном счётчике: частей
       * одновременно несколько, их события прогресса перемежаются, и
       * прибавление каждого события к общему счётчику дало бы полосу,
       * которая прыгает назад и в итоге уходит за 100%.
       *
       * Повтор части после обрыва сюда попадает сам: у индекса в map
       * одно значение, новое событие просто перезаписывает старое, и
       * две отправки одной части не сложились бы вдвое.
       */
      const inFlight = new Map()

      const reportProgress = (job, loaded) => {
        const expected = chunkLength(task.file, job.index, partSize, size)
        const partial = Math.min(loaded, expected)

        inFlight.set(job.index, partial)

        let live = 0
        inFlight.forEach((value) => {
          live += value
        })

        task.sent = Math.min(size, alreadySent + live)
        onChange(task)
      }

      await runChunks(api, jobs, concurrency, retries, reportProgress)
    }

    task.sent = size
    onChange(task)

    const completeResponse = await api.completeUpload(uploadId)
    task.result = completeResponse?.data?.data ?? null
    task.status = STATUS.DONE
    onChange(task)

    return task
  } catch (error) {
    task.status = task._cancelled ? STATUS.CANCELLED : STATUS.ERROR
    task.error = readableError(error)
    onChange(task)

    throw error
  }
}

/** Длина части с учётом того, что последняя короче остальных. */
const chunkLength = (file, index, partSize, size) => {
  const start = index * partSize

  if (start >= size) return 0

  return Math.min(partSize, size - start)
}

/** Текст ошибки из конверта ответа. */
const readableError = (error) => {
  const body = error?.response?.data

  return (
    body?.error?.message ||
    body?.data?.message ||
    body?.message ||
    error?.message ||
    'Не удалось загрузить файл'
  )
}

/**
 * Очередь загрузки с ограничением параллелизма.
 *
 * Наружу отдаётся объект с методами add/start/clear и массивом tasks.
 * Экземпляр на страницу: один менеджер на одну открытую страницу.
 */
/**
 * Очередь загрузки с ограничением параллелизма.
 *
 * СПИСОК ЗАДАЧ ПЕРЕДАЁТСЯ СНАРУЖИ (`options.tasks`) — это не
 * украшение, а необходимое условие работы во Vue.
 *
 * Реактивность в Vue 3 устроена через прокси: уведомление приходит
 * только при изменении ЧЕРЕЗ прокси. Если движок держал бы свой
 * собственный массив, он писал бы в «сырые» объекты, а интерфейс —
 * чита reactive-версию того же массива, и не увидел бы НИЧЕГО:
 * файл загрузился бы, карточек прогресса не появилось бы, список
 * задач вечно пустым. Выглядит как «загрузка сломалась», хотя запросы
 * уходят и файлы появляются.
 *
 * Поэтому: добавляем через переданный массив, а элементы всегда
 * читаем ИЗ НЕГО. Для reactive-массива это даёт прокси задачи, и
 * запись `task.status = ...` тоже уведомляет интерфейс.
 *
 * В тестах передаётся обычный массив — контракт тот же, реактивность
 * не требуется.
 */
export const createUploadQueue = (options = {}) => {
  const {
    /** Массив задач: reactive в приложении, обычный в тестах. */
    tasks = [],
    ...rest
  } = options

  const limits = {
    fileConcurrency: DEFAULT_FILE_CONCURRENCY,
    ...rest,
  }

  // Клиент по умолчанию — тот же api-слой, что и у страниц. Держим его
  // здесь явно, потому что отмена загрузки идёт мимо uploadTask и без
  // ссылки на клиент уехала бы в настоящую сеть мимо подставленного.
  const client = limits.client ?? { initUpload, sendChunk, completeUpload, abortUpload }

  let active = 0

  const pump = () => {
    while (active < limits.fileConcurrency) {
      // Читаем ИЗ tasks: для reactive-массива это отдаёт прокси задачи,
      // и её обновления видны интерфейсу.
      const next = tasks.find((task) => task.status === STATUS.QUEUED)

      if (!next) return

      active++
      uploadTask(next, { ...limits, client, onChange: () => limits.onChange?.(next) })
        .catch(() => {
          // Ошибка уже записана в задачу и показана интерфейсом.
          // Повторно бросать нельзя: необработанное отклонение упало
          // бы в консоль и засчиталось тестам как ошибка приложения.
        })
        .finally(() => {
          active--
          pump()
        })
    }
  }

  /** Кладёт задачу в список и отдаёт ссылку ИЗ списка. */
  const put = (file, meta) => {
    tasks.push(createTask(file, meta))

    return tasks[tasks.length - 1]
  }

  return {
    tasks,
    limits,
    add(file, meta) {
      const task = put(file, meta)
      pump()

      return task
    },
    /** Ставит в очередь несколько файлов сразу. */
    addAll(files, meta) {
      const start = tasks.length

      Array.from(files || [])
        .filter(isUsableFile)
        .forEach((file) => tasks.push(createTask(file, meta)))

      // Ссылки берутся из списка, а не из промежуточного массива: см.
      // объяснение выше про прокси.
      const added = tasks.slice(start)
      pump()

      return added
    },
    /**
     * Убирает задачу из очереди.
     *
     * Если файл уже отправляется, отправка не прерывается: сервер
     * дособерёт его при следующем init с теми же метаданными, а лишние
     * части просто не приедут. Синхронно прервать можно только то, что
     * ещё не началось.
     */
    remove(task) {
      task._cancelled = true

      if (task.status === STATUS.QUEUED) {
        task.status = STATUS.CANCELLED
      }

      // Поиск по id, а не indexOf: для reactive-массива indexOf
      // чувствителен к тому, прокси это или сырой объект, и при
      // несовпадении молча ничего не удалил бы.
      const index = tasks.findIndex((item) => item.id === task.id)

      if (index !== -1) {
        tasks.splice(index, 1)
      }

      if (task.uploadId) {
        client.abortUpload(task.uploadId).catch(() => {
          // Очистка частей не удалась — это не повод показывать ошибку:
          // файла всё равно не будет, а мусор уберёт files:prune-uploads.
        })
      }
    },
    /** Убирает завершённые и ошибочные задачи из списка. */
    clearFinished() {
      for (let i = tasks.length - 1; i >= 0; i--) {
        if (tasks[i].status === STATUS.DONE || tasks[i].status === STATUS.ERROR) {
          tasks.splice(i, 1)
        }
      }
    },
    get activeCount() {
      return active
    },
  }
}

export default { createUploadQueue, uploadTask, createTask, STATUS }
