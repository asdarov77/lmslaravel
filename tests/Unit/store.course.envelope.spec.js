import { describe, it, expect, vi, beforeEach } from 'vitest'

const api = vi.hoisted(() => ({
  login: vi.fn(),
  fetchCourses: vi.fn(),
  fetchCourse: vi.fn(),
  fetchCategories: vi.fn(),
  fetchCategory: vi.fn(),
  createCourse: vi.fn(),
  createCategory: vi.fn(),
  updateCourse: vi.fn(),
  updateCategory: vi.fn(),
  deleteCourse: vi.fn(),
  deleteCategory: vi.fn(),
  fetchAircrafts: vi.fn(),
  fetchAircraft: vi.fn(),
  createAircraft: vi.fn(),
  fetchGroup2learnings: vi.fn(),
  addGroup2learning: vi.fn(),
  deleteGroup2learning: vi.fn(),
  getPermissions: vi.fn(),
  getAllPermissions: vi.fn(),
}))

vi.mock('../../resources/js/api/course.api', () => api)
vi.mock('../../resources/js/api/auth.api', () => ({ login: api.login, logout: vi.fn() }))

import CourseModule from '../../resources/js/Store/modules/CourseModule'

// Так выглядит реальный ответ Laravel с middleware ApiResponseEnvelope
const envelope = (data, meta = null) => ({
  data: { success: true, data, error: null, meta },
})

beforeEach(() => {
  vi.clearAllMocks()
})

const createContext = (state) => ({
  state,
  commit: (mutation, payload) => {
    if (CourseModule.mutations[mutation]) {
      CourseModule.mutations[mutation](state, payload)
    } else {
      throw new Error('Неизвестная мутация: ' + mutation)
    }
  },
  dispatch: () => {},
})

const run = async (name, payload, state) => {
  const context = createContext(state || CourseModule.state())
  await CourseModule.actions[name](context, payload)
  return context.state
}

describe('CourseModule: fetchCategories — регресс "categories.sort is not a function"', () => {
  it('кладёт в state массив, а не конверт', async () => {
    api.fetchCategories.mockResolvedValue(
      envelope([{ id: 2, title: 'Б' }, { id: 1, title: 'А' }])
    )

    const state = await run('fetchCategories')

    expect(Array.isArray(state.categories)).toBe(true)
    expect(state.categories).toHaveLength(2)
    expect(state.categories.success).toBeUndefined()
    // мутация сортирует по id
    expect(state.categories.map((c) => c.id)).toEqual([1, 2])
    expect(state.totalCategories).toBe(2)
  })

  it('не падает на пустом массиве (и не падал бы на конверте)', async () => {
    api.fetchCategories.mockResolvedValue(envelope([]))
    const state = await run('fetchCategories')
    expect(state.categories).toEqual([])
    expect(() => state.categories.sort((a, b) => a.id - b.id)).not.toThrow()
  })

  it('не кладёт конверт, когда полезной нагрузки нет', async () => {
    // Регресс: response.data.length → TypeError, либо в state объект
    api.fetchCategories.mockResolvedValue(envelope(null))
    const state = await run('fetchCategories')
    expect(Array.isArray(state.categories)).toBe(true)
    expect(state.categories).toEqual([])
  })
})

describe('CourseModule: остальные экшены разворачивают конверт', () => {
  it('fetchCategory кладёт объект', async () => {
    api.fetchCategory.mockResolvedValue(envelope({ id: 3, title: 'Категория' }))
    const state = await run('fetchCategory', 3)
    expect(state.category).toEqual({ id: 3, title: 'Категория' })
  })

  it('fetchAircrafts кладёт массив', async () => {
    api.fetchAircrafts.mockResolvedValue(envelope([{ id: 1, path: 'БПЛА' }]))
    const state = await run('fetchAircrafts')
    expect(state.aircrafts).toEqual([{ id: 1, path: 'БПЛА' }])
    expect(state.totalAircrafts).toBe(1)
  })

  it('fetchAircraft кладёт объект', async () => {
    api.fetchAircraft.mockResolvedValue(envelope({ id: 1, path: 'КЛЕН' }))
    const state = await run('fetchAircraft', 1)
    expect(state.aircraft).toEqual({ id: 1, path: 'КЛЕН' })
  })

  it('fetchCourses читает пагинацию из meta и кладёт массив', async () => {
    api.fetchCourses.mockResolvedValue(
      envelope([{ id: 1, title: 'Курс' }], { pagination: { page: 1, perPage: 15, total: 1, totalPages: 1 } })
    )
    const state = await run('fetchCourses', {})
    expect(state.courses).toEqual([{ id: 1, title: 'Курс' }])
    expect(state.totalCourses).toBe(1)
    expect(state.pagination.total).toBe(1)
  })

  it('fetchCourses с data === null не кладёт конверт', async () => {
    api.fetchCourses.mockResolvedValue(envelope(null))
    const state = await run('fetchCourses', {})
    expect(Array.isArray(state.courses)).toBe(true)
    expect(state.courses).toEqual([])
  })

  it('createCourse кладёт созданный курс', async () => {
    api.createCourse.mockResolvedValue(envelope({ id: 9, title: 'Новый' }))
    const state = await run('createCourse', { title: 'Новый' })
    expect(state.course.id).toBe(9)
  })

  it('createCategory добавляет категорию в список, а не заменяет его конвертом', async () => {
    api.fetchCategories.mockResolvedValue(envelope([{ id: 1, title: 'А' }]))
    const state = await run('fetchCategories')
    expect(state.categories).toHaveLength(1)

    api.createCategory.mockResolvedValue(envelope({ id: 2, title: 'Б' }))
    await run('createCategory', { title: 'Б' }, state)

    expect(Array.isArray(state.categories)).toBe(true)
    expect(state.categories.map((c) => c.id)).toEqual([1, 2])
  })

  it('fetchGroup2learnings кладёт массив', async () => {
    api.fetchGroup2learnings.mockResolvedValue(envelope([{ id: 1, course_id: 2 }]))
    const state = await run('fetchGroup2learnings', { group_id: 1 })
    expect(state.group2learnings).toEqual([{ id: 1, course_id: 2 }])
  })
})

describe('CourseModule: мутации безопасны для шаблонов', () => {
  it('SET_ALL_CATEGORIES не падает на мусоре (это был баг Categories.vue)', () => {
    const state = CourseModule.state()
    expect(() => CourseModule.mutations.SET_ALL_CATEGORIES(state, null)).not.toThrow()
    expect(state.categories).toEqual([])

    expect(() => CourseModule.mutations.SET_ALL_CATEGORIES(state, { success: true, data: [] })).not.toThrow()
    expect(state.categories).toEqual([])
  })

  it('SET_COURSES всегда даёт массив', () => {
    const state = CourseModule.state()
    CourseModule.mutations.SET_COURSES(state, null)
    expect(state.courses).toEqual([])
  })

  it('UPDATE_COURSE не падает, когда курса нет в списке', () => {
    // Регресс: state.courses[-1] -> undefined, присваивание в него -> TypeError
    const state = CourseModule.state()
    state.courses = [{ id: 1, title: 'А' }]
    expect(() => CourseModule.mutations.UPDATE_COURSE(state, { id: 999, title: 'Нет' })).not.toThrow()
  })
})
