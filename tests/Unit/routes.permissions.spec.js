import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

import routes from '../../resources/js/Router/routes'

const here = dirname(fileURLToPath(import.meta.url))
const root = resolve(here, '../..')

/**
 * slug'и прав читаются из config/permissions.php — того же каталога,
 * которым пользуется бэкенд. Тест гарантирует, что фронтовые
 * meta.permission не содержат опечаток: иначе guard тихо никому
 * не давал бы доступ (или, наоборот, закрывал нужный раздел).
 */
const catalogSlugs = (() => {
  const php = readFileSync(resolve(root, 'config/permissions.php'), 'utf8')
  const block = php.slice(php.indexOf("'permissions' => ["))
  // Ключи вида 'slug' => ['name' => ..., ...] на верхнем уровне массива
  const slugs = new Set()
  for (const m of block.matchAll(/^\s{8}'([a-z_]+(?:\.[a-z_]+)?)'\s*=>\s*\[/gm)) {
    slugs.add(m[1])
  }
  return slugs
})()

/** Маршруты, которые по замыслу доступны всем (публичные и служебные). */
const PUBLIC_ROUTES = new Set(['/', '/login', '/reg', '/about', '/my', '/logout', '/datepicker', '/403', '/404', '/500'])

/**
 * Маршруты, доступные только авторизованному, но без доменного права.
 *
 * Право нужно там, где решение принимает RBAC. У маршрутов ниже решения
 * нет: пользователь читает СВОИ данные, а область данных ограничена на
 * сервере (CourseVisibility и выборка по group_id). Навешивать на них
 * content.view было бы произвольно — человек без этого права всё равно
 * видит свой план, а человек с ним не должен видеть чужих.
 *
 *  - /my, /logout, /datepicker — служебные;
 *  - /my/learning — учебный план самого обучаемого;
 *  - /dashboard — личный кабинет самого обучаемого;
 *  - /my/exams — свои экзамены;
 *  - /exams/:idEdit — прохождение своего экзамена.
 *
 * У /my/exams и /exams/:idEdit область видимости задаёт ExamController:
 * пользователю отдаются только те экзамены, которые назначены ему или его
 * группе. Навешивать content.view было бы произвольно: право «сдать
 * свой экзамен» не должно зависеть от права смотреть контент, и наоборот.
 */
const AUTH_ONLY_ROUTES = new Set([
  '/my', '/logout', '/datepicker',
  '/my/learning', '/dashboard',
  '/my/exams', '/exams/:idEdit',
  // Сертификаты: область видимости задаёт CertificateController —
  // он отдаёт только курсы из плана группы самого пользователя и
  // закрывает чужой ответом 404. Навешивать content.view здесь так же
  // произвольно, как для /my/exams.
  '/my/certificates', '/my/certificates/:idEdit',
  // Объявления: право announcements.manage нужно только на публикацию.
  // Читать ленту может любой вошедший, а видимость объявления решает
  // Announcement::visibilityFor — так же, как /my/exams не требует
  // content.view.
  '/announcements',
])

const flat = (routes || []).flat(Infinity).filter(Boolean)

describe('frontend RBAC: meta.permission согласовано с бэкенд-каталогом', () => {
  it('каталог прав прочитан из config/permissions.php', () => {
    expect(catalogSlugs.size).toBeGreaterThan(20)
    expect(catalogSlugs.has('users.view')).toBe(true)
    expect(catalogSlugs.has('courses.manage')).toBe(true)
  })

  it('каждый защищённый маршрут объявляет meta.permission', () => {
    const unprotected = flat
      .filter(r => r && r.path && !PUBLIC_ROUTES.has(r.path))
      // AUTH_ONLY_ROUTES здесь учитывается намеренно: раньше константа
      // использовалась только в третьей проверке, из-за чего маршруты
      // «авторизован, но без доменного права» приходилось объявлять
      // meta.permission с произвольным slug'ом.
      .filter(r => !AUTH_ONLY_ROUTES.has(r.path))
      .filter(r => !Array.isArray(r.meta?.permission) || r.meta.permission.length === 0)
      .map(r => r.path)

    expect(unprotected, `маршруты без meta.permission: ${unprotected.join(', ')}`).toEqual([])
  })

  it('все slug из meta.permission существуют в бэкенд-каталоге', () => {
    const unknown = []
    for (const route of flat) {
      const perms = route?.meta?.permission
      if (!Array.isArray(perms)) continue
      for (const p of perms) {
        if (!catalogSlugs.has(p)) unknown.push(`${route.path}: ${p}`)
      }
    }
    expect(unknown, `неизвестные права: ${unknown.join(', ')}`).toEqual([])
  })

  it('публичные маршруты не требуют прав (иначе блокируется логин/регистрация)', () => {
    for (const route of flat) {
      if (!route?.path) continue
      if (PUBLIC_ROUTES.has(route.path) && !AUTH_ONLY_ROUTES.has(route.path)) {
        expect(route.meta?.permission, `${route.path} не должен требовать прав`).toBeUndefined()
      }
    }
  })

  it('маршруты администрирования защищены доменными правами', () => {
    const expectPerm = (path, ...allowed) => {
      const routesForPath = flat.filter(r => r?.path === path)
      expect(routesForPath.length, `маршрут ${path} не найден`).toBeGreaterThan(0)
      for (const route of routesForPath) {
        const perms = route.meta?.permission || []
        expect(perms.length, `${path} без meta.permission`).toBeGreaterThan(0)
        expect(
          perms.some(p => allowed.includes(p)),
          `${path} требует ${JSON.stringify(perms)}, ожидалось одно из ${allowed.join('/')}`,
        ).toBe(true)
      }
    }

    expectPerm('/groups/list', 'groups.view', 'users.view')
    expectPerm('/courses/list', 'courses.view')
    expectPerm('/classes', 'content.manage')
    expectPerm('/filemanager', 'files.upload', 'content.manage')
    expectPerm('/user-course/:id', 'users.courses')
  })

  it('backend остаётся источником истины: guard — только UX-слой', () => {
    // Напоминание-инвариант: если guard закрыл маршрут, бэкенд всё равно
    // обязан вернуть 403. Это проверяется в tests/Feature/Api/
    // RoutePermissionCoverageTest.php на стороне PHP.
    expect(flat.length).toBeGreaterThan(30)
  })
})
