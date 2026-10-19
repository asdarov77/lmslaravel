// @vitest-environment jsdom
/**
 * Боковое меню: видимость пунктов по правам.
 *
 * Тесты проверяют функцию `visibleSections` (и `visibleItems`) напрямую.
 * Это чистая логика без зависимостей от рендеринга Vue, и она покрывает
 * весь функционал фильтрации по правам и ролям.
 *
 * Раньшие тесты монтировали LeftSideMenu, что было хрупко: компонент
 * читает localStorage, требует $route, и заголовки пунктов скрываются
 * при collapsed=false. Прямое тестирование утилиты избавляет от всего
 * этого шума.
 */
import { describe, it, expect } from 'vitest'
import { visibleSections, visibleItems } from '../../resources/js/utils/navigationVisibility'
import { routePermissionIndex, requiredPermissions } from '../../resources/js/utils/routePermissions'
import { navigationSections } from '../../resources/js/navigation'

const index = routePermissionIndex()

/**
 * Фабрика геттера `can` для тестов.
 *
 * @param {string[]} permissions — слаги прав пользователя
 */
const makeCan = permissions => (...perms) => {
  const required = perms.flat().filter(Boolean).map(String)
  if (required.length === 0) return true
  if (Array.isArray(perms[0])) {
    return required.every(slug => permissions.includes(slug))
  }
  return required.some(slug => permissions.includes(slug))
}

/**
 * Контекст для visibleSections / visibleItems.
 */
const ctx = (permissions, isManager) => ({
  can: makeCan(permissions),
  isManager,
  t: key => key, // заглушка перевода — возвращает сам ключ
  index,
  requiredBy: link => requiredPermissions(index, link),
})

/**
 * Ключи видимых пунктов (item.key) — удобнее для проверок,
 * чем заголовки.
 */
const visibleKeys = (permissions, isManager) =>
  visibleSections(navigationSections, ctx(permissions, isManager))
    .flatMap(s => s.items)
    .map(i => i.key)

describe('Боковое меню: видимость по правам (unit)', () => {
  it('обучаемому показывает только его пункты, без 403-ловушек', () => {
    // Права обучаемого по role_matrix.
    const keys = visibleKeys(
      ['courses.view', 'content.view', 'exams.take', 'dictionaries.view'],
      false
    )

    expect(keys).toContain('courses')
    expect(keys).toContain('myLearning')
    expect(keys).toContain('dashboard')
    expect(keys).toContain('exams')

    // Пункты, которые уводили обучаемого в 403.
    expect(keys).not.toContain('files')
    expect(keys).not.toContain('categories')
    expect(keys).not.toContain('users')
    expect(keys).not.toContain('groups')
    expect(keys).not.toContain('permissions')
    expect(keys).not.toContain('groupLearning')
    expect(keys).not.toContain('classes')
    expect(keys).not.toContain('calendar')
    expect(keys).not.toContain('questionbank')
  })

  it('не оставляет пустую группу «Управление пользователями»', () => {
    // У обучаемого все пункты группы «admin» отфильтрованы.
    // Раньше оставался заголовок без пунктов.
    const sections = visibleSections(
      navigationSections,
      ctx(['courses.view', 'content.view', 'exams.take', 'dictionaries.view'], false)
    )
    const adminSection = sections.find(s => s.key === 'admin')
    expect(adminSection, 'секция admin должна отсутствовать или быть пустой').toBeUndefined()
  })

  it('инструктору показывает методические пункты', () => {
    // Права инструктора по role_matrix.
    const keys = visibleKeys(
      [
        'courses.view', 'courses.manage', 'content.manage', 'files.upload',
        'categories.manage', 'groups.view', 'users.view',
        'questions.view', 'questions.manage', 'exams.take', 'exams.manage',
        'grading.manage',
      ],
      true
    )

    expect(keys).toContain('files')
    expect(keys).toContain('categories')
    expect(keys).toContain('exams')
    expect(keys).toContain('permissions')
    expect(keys).toContain('questionbank')
    expect(keys).toContain('calendar')
    // Учебный план помечен onlyFor: 'trainee' — инструктору не показывается.
    expect(keys).not.toContain('myLearning')
    expect(keys).not.toContain('gamification')
  })

  it('администратору остаётся полное меню', () => {
    // Администратор (isSuperAdmin) получает все права через can() -> true.
    // Для теста эмулируем это через полный набор прав.
    const adminPermissions = [
      'courses.view', 'courses.manage', 'courses.publish',
      'tutor.use', 'tutor.manage',
      'categories.manage', 'groups.view', 'groups.manage',
      'questions.view', 'questions.manage',
      'exams.take', 'exams.manage', 'grading.manage',
      'files.upload', 'content.view', 'content.manage',
      'certificates.manage', 'forum.moderate', 'announcements.manage',
      'reports.view', 'audit.view', 'dictionaries.view',
      'settings.manage', 'system.maintenance',
      'users.view', 'users.create', 'users.update', 'users.delete',
      'users.permissions', 'users.courses',
    ]
    const keys = visibleKeys(adminPermissions, true)

    expect(keys).toContain('files')
    expect(keys).toContain('users')
    expect(keys).toContain('groups')
    expect(keys).toContain('settings')
    expect(keys).toContain('grades')
    // У админа нет onlyFor: 'trainee' пунктов
    expect(keys).not.toContain('myLearning')
    expect(keys).not.toContain('gamification')
  })

  describe('признак onlyFor', () => {
    it('обучаемому показывает «Учебный план»', () => {
      const keys = visibleKeys(['courses.view', 'content.view', 'exams.take', 'dictionaries.view'], false)
      expect(keys).toContain('myLearning')
    })

    it('управляющему «Учебный план» не показывает лишнего', () => {
      const keys = visibleKeys(
        ['courses.view', 'content.manage', 'files.upload', 'categories.manage', 'groups.view', 'users.view', 'questions.view', 'exams.manage', 'grading.manage'],
        true
      )
      expect(keys).not.toContain('myLearning')
    expect(keys).not.toContain('gamification')
    })

    it('признак onlyFor ничего не ломает у обычного пункта', () => {
      // Пункт без onlyFor виден и manager, и trainee при наличии прав.
      const traineeKeys = visibleKeys(['courses.view'], false)
      const managerKeys = visibleKeys(['courses.view'], true)
      expect(traineeKeys).toContain('courses')
      expect(managerKeys).toContain('courses')
    })
  })
})