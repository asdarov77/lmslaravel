// @vitest-environment jsdom
/**
 * Тесты формы «Добавление класса» (resources/js/Pages/Course/AddClass.vue).
 *
 * Регресс, который закрывается этими тестами:
 *  - форма отправляла POST /api/classes с path: "" / title: "" и получала
 *    422 «The title field is required» — валидации на клиенте не было,
 *    ошибка уходила только в console.error и пользователь видел «ничего»;
 *  - v-form @submit и ButtonGroup @submitForm были двумя разными
 *    обработчиками: второй всегда делал router.go(-1), даже при ошибке;
 *  - два блока `computed` в одном SFC (второй перетирал первый, терялись
 *    mapState/mapGetters) и watcher, писавший в computed `filteredTags`
 *    («computed property filteredTags is readonly»);
 *  - кнопка очистки БД не показывала результат и не перечитывала теги.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

const http = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}))
vi.mock('../../resources/js/api/httpClient', () => ({ default: http }))

import { createStore } from 'vuex'
import { createVuetify } from 'vuetify'
import * as vuetifyComponents from 'vuetify/components'
import * as vuetifyDirectives from 'vuetify/directives'
import CourseModule from '../../resources/js/Store/modules/CourseModule'

import AddClass, { toClassPath, extractApiError } from '../../resources/js/Pages/Course/AddClass.vue'

const vuetify = createVuetify({ components: vuetifyComponents, directives: vuetifyDirectives })
const tick = (n = 0) => new Promise((r) => setTimeout(r, n))

const envelope = (data, meta = null) => ({ data: { success: true, data, error: null, meta } })
const validationError = (errors) => {
  const err = new Error('Request failed with status code 422')
  err.response = { status: 422, data: { message: 'The given data was invalid.', errors } }
  return err
}
const serverError = (status, message) => {
  const err = new Error(`Request failed with status code ${status}`)
  err.response = { status, data: { success: false, data: null, error: { code: String(status), message }, meta: null } }
  return err
}

let store
let back

beforeEach(() => {
  vi.clearAllMocks()
  back = vi.fn()
  store = createStore({ modules: { Course: CourseModule } })
  http.get.mockResolvedValue(envelope(['БПЛА', 'КЛЕН']))
})

const mountPage = () =>
  mount(AddClass, {
    store,
    vuetify,
    global: {
      mocks: {
        $store: store,
        $route: { params: {}, query: {} },
        $router: { go: back, back, push: vi.fn() },
      },
      stubs: {
        // ButtonGroup использует useI18n() в setup(); в юнит-тесте
        // плагин i18n не установлен, поэтому подменяем его заглушкой,
        // которая всё равно эмитит submitForm/cancelBtn.
        ButtonGroup: {
          name: 'ButtonGroup',
          props: ['onSubmitForm', 'onCancelBtn'],
          template: '<div class="btn-group-stub"></div>',
        },
      },
    },
  })

// --------------------------------------------------- чистые функции

describe('toClassPath: нормализация значения комбобокса', () => {
  it('строка обрезается от пробелов', () => {
    expect(toClassPath('  КЛЕН  ')).toBe('КЛЕН')
  })

  it('объект vuetify { text, value }', () => {
    expect(toClassPath({ text: 'КЛЕН', value: 'КЛЕН' })).toBe('КЛЕН')
    expect(toClassPath({ text: 'КЛЕН' })).toBe('КЛЕН')
    expect(toClassPath({ title: 'БПЛА' })).toBe('БПЛА')
    expect(toClassPath({ name: 'Ми-38' })).toBe('Ми-38')
  })

  it('массив берёт первый элемент', () => {
    expect(toClassPath(['КЛЕН'])).toBe('КЛЕН')
    expect(toClassPath([{ value: 'БПЛА' }])).toBe('БПЛА')
  })

  it('пустые значения дают null (иначе уходит 422)', () => {
    expect(toClassPath('')).toBeNull()
    expect(toClassPath('   ')).toBeNull()
    expect(toClassPath(null)).toBeNull()
    expect(toClassPath(undefined)).toBeNull()
    expect(toClassPath([])).toBeNull()
    expect(toClassPath({})).toBeNull()
    expect(toClassPath({ value: null, text: '' })).toBeNull()
  })
})

describe('extractApiError: текст ошибки из ответа API', () => {
  it('достаёт message из конверта envelope', () => {
    const err = serverError(409, 'Класс «КЛЕН» уже импортирован')
    expect(extractApiError(err)).toBe('Класс «КЛЕН» уже импортирован')
  })

  it('склеивает ошибки валидации Laravel', () => {
    const err = validationError({
      title: ['The title field is required.'],
      path: ['The path field is required.'],
    })
    expect(extractApiError(err)).toMatch(/title field is required/)
    expect(extractApiError(err)).toMatch(/path field is required/)
  })

  it('падает на понятный текст при сетевой ошибке', () => {
    const err = new Error('Network Error')
    expect(extractApiError(err, 'Очистка не удалась')).toBe('Очистка не удалась')
  })

  it('пустой ответ не превращается в «undefined»', () => {
    expect(extractApiError({ response: { status: 500, data: {} } }, 'фолбэк')).toBe('фолбэк')
  })
})

// --------------------------------------------------- загрузка тегов

describe('AddClass: загрузка списка классов', () => {
  it('разворачивает конверт /api/classesfs в массив строк', async () => {
    const wrapper = mountPage()
    await tick()

    expect(http.get).toHaveBeenCalledWith('/api/classesfs')
    expect(wrapper.vm.tags).toEqual(['БПЛА', 'КЛЕН'])
    expect(wrapper.vm.allTags).toEqual(['БПЛА', 'КЛЕН'])
  })

  it('сетевая ошибка загрузки показывается понятным текстом, а не только в консоль', async () => {
    http.get.mockRejectedValue(new Error('Network Error'))
    const wrapper = mountPage()
    await tick()

    expect(wrapper.vm.errorMessage).toBe('Не удалось загрузить список классов')
  })

  it('ошибка сервера при загрузке показывается текстом бэкенда', async () => {
    http.get.mockRejectedValue(serverError(500, 'Диск контента недоступен'))
    const wrapper = mountPage()
    await tick()

    expect(wrapper.vm.errorMessage).toBe('Диск контента недоступен')
  })
})

// ------------------------------------------------- добавление класса

describe('AddClass: добавление класса', () => {
  it('пустая форма НЕ отправляет запрос (регресс 422)', async () => {
    const wrapper = mountPage()
    await tick()

    // Нажимаем «Сохранить», ничего не заполнив
    const ok = await wrapper.vm.uploadData()
    await tick()

    expect(ok).toBe(false)
    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.vm.errorMessage).toMatch(/Выберите класс/)
    expect(back).not.toHaveBeenCalled()
  })

  it('заполнен только класс — подсказка про описание, запроса нет', async () => {
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    const ok = await wrapper.vm.uploadData()

    expect(ok).toBe(false)
    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.vm.errorMessage).toMatch(/описание/)
  })

  it('заполнено только описание — подсказка про класс, запроса нет', async () => {
    const wrapper = mountPage()
    await tick()

    wrapper.vm.title = 'Описание'
    const ok = await wrapper.vm.uploadData()

    expect(ok).toBe(false)
    expect(http.post).not.toHaveBeenCalled()
    expect(wrapper.vm.errorMessage).toMatch(/Выберите класс/)
  })

  it('успешная отправка: строка, тег исчезает из списка, показан результат', async () => {
    http.post.mockResolvedValue(envelope({ id: 1, path: 'КЛЕН' }, { auk: ['01', '02'] }))
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    wrapper.vm.title = 'Класс КЛЕН'
    const ok = await wrapper.vm.uploadData()
    await tick()

    expect(ok).toBe(true)
    expect(http.post).toHaveBeenCalledTimes(1)
    const [url, payload] = http.post.mock.calls[0]
    expect(url).toBe('/api/classes')
    expect(payload).toEqual({ title: 'Класс КЛЕН', path: 'КЛЕН' })
    expect(wrapper.vm.tags).toEqual(['БПЛА'])
    expect(wrapper.vm.successMessage).toMatch(/КЛЕН/)
    // Регресс: сводка импорта лежит в meta.auk. Раньше читался data.auks,
    // которого в data нет, и пользователю всегда показывалось «АУК: 0».
    expect(wrapper.vm.successMessage).toMatch(/Загружено АУК: 2/)
    expect(wrapper.vm.auks).toEqual(['01', '02'])
    // Поля очищены, форма готова к следующему классу
    expect(wrapper.vm.title).toBe('')
    expect(wrapper.vm.path).toBe('')
    expect(wrapper.vm.errorMessage).toBe('')
    expect(back).not.toHaveBeenCalled()
  })

  it('объект из v-combobox нормализуется до строки', async () => {
    http.post.mockResolvedValue(envelope({ id: 2, path: 'БПЛА' }, { auk: ['04'] }))
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = { text: 'БПЛА', value: 'БПЛА' }
    wrapper.vm.title = 'БПЛА класс'
    await wrapper.vm.uploadData()

    expect(http.post).toHaveBeenCalledWith(
      '/api/classes',
      { title: 'БПЛА класс', path: 'БПЛА' },
      expect.any(Object)
    )
  })

  it('409 «уже импортирован» показывается и страница не меняется', async () => {
    http.post.mockRejectedValue(serverError(409, 'Класс «КЛЕН» уже импортирован'))
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    wrapper.vm.title = 'Класс КЛЕН'
    const ok = await wrapper.vm.uploadData()

    expect(ok).toBe(false)
    expect(wrapper.vm.errorMessage).toBe('Класс «КЛЕН» уже импортирован')
    // Поля сохраняем, чтобы пользователь мог исправить и повторить
    expect(wrapper.vm.path).toBe('КЛЕН')
    expect(back).not.toHaveBeenCalled()
  })

  it('422 от сервера показывается текстом ошибки', async () => {
    http.post.mockRejectedValue(
      validationError({ title: ['The title field is required.'] })
    )
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    wrapper.vm.title = 'x'
    await wrapper.vm.uploadData()

    expect(wrapper.vm.errorMessage).toMatch(/title field is required/)
  })

  it('submit формы (Enter) ведёт в тот же сценарий, что и кнопка', async () => {
    http.post.mockResolvedValue(envelope({ id: 1, path: 'КЛЕН' }, { auk: ['01'] }))
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    wrapper.vm.title = 'Класс КЛЕН'
    await wrapper.vm.submitForm()

    expect(http.post).toHaveBeenCalledTimes(1)
    // Регресс: раньше submit делал router.go(-1) даже при ошибке
    expect(back).not.toHaveBeenCalled()
  })

  it('canSubmit отражает заполненность формы', async () => {
    const wrapper = mountPage()
    await tick()

    expect(wrapper.vm.canSubmit).toBe(false)
    wrapper.vm.path = 'КЛЕН'
    expect(wrapper.vm.canSubmit).toBe(false)
    wrapper.vm.title = 'Описание'
    expect(wrapper.vm.canSubmit).toBe(true)
  })

  it('loading сбрасывается даже при ошибке', async () => {
    http.post.mockRejectedValue(serverError(500, 'Ошибка импорта'))
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'КЛЕН'
    wrapper.vm.title = 'Класс'
    await wrapper.vm.uploadData()

    expect(wrapper.vm.loading).toBe(false)
    expect(wrapper.vm.progress).toBe(0)
  })
})

// ---------------------------------------------------- очистка БД

describe('AddClass: очистка базы данных', () => {
  it('успех: сообщение и возврат полного списка тегов', async () => {
    http.post.mockResolvedValue(envelope({ message: 'Database cleared successfully' }))
    const wrapper = mountPage()
    await tick()

    // Сначала «импортировали» КЛЕН — тег пропал из списка
    wrapper.vm.tags = ['БПЛА']
    await wrapper.vm.clearDatabase()
    await tick()

    expect(http.post).toHaveBeenCalledWith('/api/clear-database')
    expect(wrapper.vm.successMessage).toMatch(/очищена/i)
    expect(wrapper.vm.errorMessage).toBe('')
    // Список перечитан с сервера и снова полный
    expect(wrapper.vm.tags).toEqual(['БПЛА', 'КЛЕН'])
    expect(wrapper.vm.clearing).toBe(false)
  })

  it('ошибка очистки показывается пользователю', async () => {
    http.post.mockRejectedValue(serverError(403, 'Недостаточно прав'))
    const wrapper = mountPage()
    await tick()

    await wrapper.vm.clearDatabase()

    expect(wrapper.vm.errorMessage).toBe('Недостаточно прав')
    expect(wrapper.vm.successMessage).toBe('')
    expect(wrapper.vm.clearing).toBe(false)
  })

  it('повторный клик во время очистки блокируется флагом clearing', async () => {
    let release
    http.post.mockImplementation(
      () =>
        new Promise((resolve) => {
          release = () => resolve(envelope({}))
        })
    )
    const wrapper = mountPage()
    await tick()

    const first = wrapper.vm.clearDatabase()
    expect(wrapper.vm.clearing).toBe(true)
    // Второй клик игнорируется, пока предыдущий запрос в полёте
    const second = wrapper.vm.clearDatabase()
    release()
    await Promise.all([first, second])

    expect(http.post).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.clearing).toBe(false)
  })
})

// --------------------------------------------- прочее (регрессы)

describe('AddClass: прочие регрессы', () => {
  it('filteredTags фильтрует по вводу и не падает на пустом', async () => {
    const wrapper = mountPage()
    await tick()

    wrapper.vm.path = 'кл'
    await tick()
    expect(wrapper.vm.filteredTags).toEqual(['КЛЕН'])

    // Регресс: watcher писал в computed filteredTags, и Vue ругался
    // "computed property filteredTags is readonly". Проверяем, что
    // setter'а нет и значение остаётся вычисляемым.
    expect(wrapper.vm.$options.watch).toBeUndefined()
    wrapper.vm.path = ''
    await tick()
    expect(wrapper.vm.filteredTags).toEqual(['БПЛА', 'КЛЕН'])
  })

  it('кнопка «Отмена» возвращает назад', async () => {
    const wrapper = mountPage()
    await tick()

    wrapper.vm.cancelBtnHead()
    expect(back).toHaveBeenCalledWith(-1)
  })

  it('в SFC ровно один блок computed (второй раньше перетирал первый)', async () => {
    const { readFileSync } = await import('node:fs')
    const file = AddClass.__file || 'resources/js/Pages/Course/AddClass.vue'
    const source = readFileSync(file, 'utf-8')
    expect(source.match(/^\s{2}computed:\s*{/gm) || []).toHaveLength(1)
  })
})