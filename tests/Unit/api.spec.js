import { describe, it, expect, vi, beforeEach } from 'vitest'

const get = vi.fn()
const post = vi.fn()
const put = vi.fn()
const patch = vi.fn()
const del = vi.fn()

vi.mock('../../resources/js/api/httpClient', () => ({
  default: {
    get: (...a) => get(...a),
    post: (...a) => post(...a),
    put: (...a) => put(...a),
    patch: (...a) => patch(...a),
    delete: (...a) => del(...a),
  },
}))

import * as userApi from '../../resources/js/api/user.api'
import * as courseApi from '../../resources/js/api/course.api'
import * as authApi from '../../resources/js/api/auth.api'

beforeEach(() => vi.clearAllMocks())

// --------------------------------------------------------------- USER API

describe('user.api: URL и методы', () => {
  it('fetchUsers шлёт POST /api/user/list', () => {
    userApi.fetchUsers()
    expect(post).toHaveBeenCalledWith('/api/user/list', {})
  })

  it('fetchUsers пробрасывает параметры', () => {
    userApi.fetchUsers({ page: 2 })
    expect(post).toHaveBeenCalledWith('/api/user/list', { page: 2 })
  })

  it('fetchUser подставляет id в путь', () => {
    userApi.fetchUser(37)
    expect(get).toHaveBeenCalledWith('/api/user/list/37')
  })

  it('updateUser использует PATCH', () => {
    userApi.updateUser(37, { fio: 'X' })
    expect(patch).toHaveBeenCalledWith('/api/user/37', { fio: 'X' })
  })

  it('createUser идёт в /api/register', () => {
    userApi.createUser({ fio: 'Новый' })
    expect(post).toHaveBeenCalledWith('/api/register', { fio: 'Новый' })
  })

  it('deleteUser использует DELETE', () => {
    userApi.deleteUser(5)
    expect(del).toHaveBeenCalledWith('/api/user/5')
  })

  it('chpassUser шлёт PUT с паролем', () => {
    userApi.chpassUser(5, { password: 'newpass' })
    expect(put).toHaveBeenCalledWith('/api/user/chpass/5', { password: 'newpass' })
  })
})

describe('user.api: группы и права', () => {
  it('fetchGroups без аргументов', () => {
    userApi.fetchGroups()
    expect(get).toHaveBeenCalledWith('/api/groups')
  })

  it('fetchGroup подставляет id', () => {
    userApi.fetchGroup(3)
    expect(get).toHaveBeenCalledWith('/api/groups/3')
  })

  it('createGroup шлёт POST с данными', () => {
    userApi.createGroup({ groupname: 'Группа' })
    expect(post).toHaveBeenCalledWith('/api/groups', { groupname: 'Группа' })
  })

  it('updateGroup использует PUT', () => {
    userApi.updateGroup(3, { groupname: 'Новая' })
    expect(put).toHaveBeenCalledWith('/api/groups/3', { groupname: 'Новая' })
  })

  it('deleteGroup использует DELETE', () => {
    userApi.deleteGroup(3)
    expect(del).toHaveBeenCalledWith('/api/groups/3')
  })

  it('fetchPermissions берёт /api/permissions', () => {
    userApi.fetchPermissions()
    expect(get).toHaveBeenCalledWith('/api/permissions')
  })
})

// ------------------------------------------------------------- COURSE API

describe('course.api: курсы', () => {
  it('fetchCourses передаёт params объектом', () => {
    courseApi.fetchCourses({ category_id: 2 })
    expect(get).toHaveBeenCalledWith('/api/course', { params: { category_id: 2 } })
  })

  it('fetchCourses без аргументов шлёт пустой params', () => {
    courseApi.fetchCourses()
    expect(get).toHaveBeenCalledWith('/api/course', { params: {} })
  })

  it('fetchCourse подставляет id', () => {
    courseApi.fetchCourse(9)
    expect(get).toHaveBeenCalledWith('/api/course/9')
  })

  it('fetchCoursecat берёт courses/cat/{id}', () => {
    courseApi.fetchCoursecat(4)
    expect(get).toHaveBeenCalledWith('/api/courses/cat/4')
  })

  it('фильтры по курсу и категории собирают query-строку', () => {
    courseApi.fetchCourseByCourseAndCategory(1, 2)
    expect(get).toHaveBeenCalledWith('/api/course?course_id=1&category_id=2')

    courseApi.fetchCourseByCourse(1)
    expect(get).toHaveBeenCalledWith('/api/course?course_id=1')

    courseApi.fetchCourseByCategory(2)
    expect(get).toHaveBeenCalledWith('/api/course?category_id=2')
  })

  it('createCourse / updateCourse / deleteCourse используют верные методы', () => {
    courseApi.createCourse({ title: 'Курс' })
    expect(post).toHaveBeenCalledWith('/api/course', { title: 'Курс' })

    courseApi.updateCourse(1, { title: 'Курс' })
    expect(patch).toHaveBeenCalledWith('/api/course/1', { title: 'Курс' })

    courseApi.deleteCourse(1)
    expect(del).toHaveBeenCalledWith('/api/course/1')
  })
})

describe('course.api: категории', () => {
  it('fetchCategories без аргументов', () => {
    courseApi.fetchCategories()
    expect(get).toHaveBeenCalledWith('/api/categories')
  })

  it('fetchCategory подставляет id', () => {
    courseApi.fetchCategory(6)
    expect(get).toHaveBeenCalledWith('/api/categories/6')
  })

  it('createCategory использует POST', () => {
    courseApi.createCategory({ title: 'Категория' })
    expect(post).toHaveBeenCalledWith('/api/categories', { title: 'Категория' })
  })

  it('updateCategory использует PUT', () => {
    courseApi.updateCategory(6, { title: 'Новая' })
    expect(put).toHaveBeenCalledWith('/api/categories/6', { title: 'Новая' })
  })

  it('deleteCategory использует DELETE', () => {
    courseApi.deleteCategory(6)
    expect(del).toHaveBeenCalledWith('/api/categories/6')
  })
})

describe('course.api: борта (aircrafts)', () => {
  it('fetchAircrafts берёт /api/classes', () => {
    courseApi.fetchAircrafts()
    expect(get).toHaveBeenCalledWith('/api/classes')
  })

  it('fetchAircraft подставляет id', () => {
    courseApi.fetchAircraft(2)
    expect(get).toHaveBeenCalledWith('/api/classes/2')
  })

  it('createAircraft / updateAircraft / deleteAircraft', () => {
    courseApi.createAircraft({ path: 'БПЛА' })
    expect(post).toHaveBeenCalledWith('/api/classes', { path: 'БПЛА' })

    courseApi.updateAircraft(2, { path: 'КЛЕН' })
    expect(put).toHaveBeenCalledWith('/api/classes/2', { path: 'КЛЕН' })

    courseApi.deleteAircraft(2)
    expect(del).toHaveBeenCalledWith('/api/classes/2')
  })
})

describe('course.api: групповое обучение', () => {
  it('fetchGroup2learnings шлёт POST с данными', () => {
    courseApi.fetchGroup2learnings({ group_id: 1 })
    expect(post).toHaveBeenCalledWith('/api/group/learning', { group_id: 1 })
  })

  /**
   * Регресс: в пути deleteGroup2learnings не было ведущего слэша,
   * из-за чего axios склеивал baseURL и URL в «http://hostapi/learning/1».
   */
  it('deleteGroup2learnings строит абсолютный путь со слэшем', () => {
    courseApi.deleteGroup2learnings(1)
    const [url] = del.mock.calls[0]
    expect(url).toBe('/api/learning/1')
    expect(url.startsWith('/')).toBe(true)
  })
})

// --------------------------------------------------------------- AUTH API

describe('auth.api', () => {
  it('login шлёт POST /api/login с логином и паролем', () => {
    authApi.login({ fio: 'Иван', password: 'secret' })
    expect(post).toHaveBeenCalledWith('/api/login', { fio: 'Иван', password: 'secret' })
  })
})
