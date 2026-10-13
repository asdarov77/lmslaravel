// @vitest-environment jsdom
/**
 * Общая видимость пунктов навигации.
 *
 * Хелпер обслуживает и боковое меню, и палитру команд. Расхождение
 * между ними — самый неприятный вид регрессии: человек видит в палитре
 * раздел, которого у него нет, и получает 403 по нажатию. Поэтому
 * проверяется именно общий источник решения.
 */
import { describe, it, expect } from 'vitest'
import { navigationSections } from '../../resources/js/navigation'
import {
  allowsLink,
  fitsRole,
  visibleItems,
  visibleSections,
} from '../../resources/js/utils/navigationVisibility'

const index = new Map([
  ['/files', ['files.upload', 'courses.manage']],
  ['/users', ['users.view']],
  ['/dashboard', []],
])

const requiredBy = (link) => index.get(link) ?? null

const ctxWith = (permissions) => ({
  can: (...perms) => permissions.some((slug) => permissions.includes(slug)),
  isManager: false,
  t: (key) => `t:${key}`,
  index,
  requiredBy,
  permissions,
})

describe('navigationVisibility — общая видимость пунктов', () => {
  it('скрывает пункт, если не хватает прав', () => {
    // can() реализует семантику геттера стора: массив — AND, строка — OR.
    const ctx = {
      ...ctxWith([]),
      can: (...perms) => {
        const required = perms.flat().filter(Boolean).map(String)

        return required.length === 0 || required.some((slug) => ctx.permissions.includes(slug))
      },
    }

    expect(allowsLink('/files', index, ctx.can, requiredBy)).toBe(false)
    expect(allowsLink('/dashboard', index, ctx.can, requiredBy)).toBe(true)
  })

  it('показывает пункт, когда хватает одного из требуемых прав', () => {
    // У /files требования files.upload + courses.manage.
    const ctx = ctxWith(['files.upload'])
    ctx.can = (...perms) => {
      const required = perms.flat().filter(Boolean).map(String)

      return required.some((slug) => ctx.permissions.includes(slug))
    }

    expect(allowsLink('/files', index, ctx.can, requiredBy)).toBe(true)
  })

  /**
   * onlyFor понимает ровно 'manager' и всё остальное.
   *
   * Любой другой список (например ['manager','methodologist']) молча
   * проходил бы как «не manager» и показывал пункт всем, включая
   * обучаемого.
   */
  it('отбирает по роли, а не по первому слову списка', () => {
    const managerItem = { onlyFor: 'manager' }
    const traineeItem = { onlyFor: 'trainee' }
    const noRoleItem = {}

    expect(fitsRole(managerItem, true)).toBe(true)
    expect(fitsRole(managerItem, false)).toBe(false)
    expect(fitsRole(traineeItem, false)).toBe(true)
    expect(fitsRole(traineeItem, true)).toBe(false)
    expect(fitsRole(noRoleItem, false)).toBe(true)
    expect(fitsRole(undefined, false)).toBe(true)
  })

  it('переводит названия: вместо ключа показывается текст', () => {
    const items = [{ key: 'files', titleKey: 'app.menu.files', link: '/files' }]

    const visible = visibleItems(items, {
      can: () => true,
      isManager: false,
      // Переводчик отдаёт название; если бы title остался titleKey,
      // в меню и палитре было бы видно «app.menu.files».
      t: (key) => (key === 'app.menu.files' ? 'Файлы' : key),
      index,
      requiredBy,
    })

    expect(visible[0].title).toBe('Файлы')
  })

  it('убирает пустые секции целиком', () => {
    const sections = [
      { key: 'learning', items: [{ key: 'dashboard', titleKey: 'app.menu.dashboard', link: '/dashboard' }] },
      { key: 'manage', items: [{ key: 'users', titleKey: 'app.menu.users', link: '/users' }] },
    ]

    const visible = visibleSections(sections, {
      can: () => false,
      isManager: false,
      t: (key) => `t:${key}`,
      index,
      requiredBy,
    })

    // Заголовок «Управление» без пунктов занимал бы строку и вводил бы
    // в заблуждение: казалось бы, что раздел пуст из-за прав, а не
    // потому что его нет.
    expect(visible).toHaveLength(1)
    expect(visible[0].key).toBe('learning')
  })

  it('один и тот же контекст даёт одинаковый результат для меню и палитры', () => {
    // Палитра строит команды из того же хелпера. Если бы она брала
    // сырой navigationSections, обучаемому показывался бы раздел,
    // которого в меню у него нет.
    const ctx = {
      can: (...perms) => perms.includes('content.view'),
      isManager: false,
      t: (key) => `t:${key}`,
      index,
      requiredBy,
    }

    const menuLinks = visibleSections(navigationSections, ctx)
      .flatMap((section) => section.items.map((item) => item.link))

    const paletteLinks = visibleSections(navigationSections, ctx)
      .flatMap((section) => section.items.map((item) => item.link))

    expect(paletteLinks).toEqual(menuLinks)
  })
})
