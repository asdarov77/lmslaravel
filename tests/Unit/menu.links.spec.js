// @vitest-environment jsdom
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { createRouter, createMemoryHistory } from 'vue-router'

import routes from '../../resources/js/Router/routes'
import ru from '../../resources/js/locales/ru.json'
import en from '../../resources/js/locales/en.json'

const here = dirname(fileURLToPath(import.meta.url))
const root = resolve(here, '../..')
const menuSource = readFileSync(
  resolve(root, 'resources/js/Pages/Navigation/LeftSideMenu.vue'),
  'utf8'
)

/**
 * Ссылки бокового меню должны вести на существующие маршруты.
 *
 * Регресс, который закрывает этот файл:
 *
 *  1. Пункт «user learning» вёл на /user/learning — такого path в
 *     Router/routes.js нет, клик открывал страницу 404. При этом у него
 *     был contentType: " ", поэтому пункт показывался вообще всем.
 *
 *  2. Два пункта назывались «Курсы» (/courses/list и /auk). Vuetify
 *     строит id узла из заголовка, поэтому в консоли было
 *     «Vuetify error: Multiple nodes with the same ID».
 *
 *  3. Заголовки хардкодились литералами («Экзамен», «Банк вопросов»),
 *     тогда как остальные пункты берутся из i18n.
 *
 * Здесь мы разбираем меню статически (без монтирования Vuetify) и
 * сопоставляем ссылки с реальными путями роутера.
 */
const flat = (routes || []).flat(Infinity).filter(Boolean)

/** Пункты меню в порядке объявления: пары { link, title }. */
const menuItems = (() => {
  const items = []
  // Блоки computed menuContent / menuUsers: от title: ... до link: "..."
  const blockRe = /title:\s*(?:this\.\$t\("([^"]+)"\)|"([^"]+)")\s*,\s*\n\s*link:\s*"([^"]+)"/g
  for (const m of menuSource.matchAll(blockRe)) {
    items.push({ i18nKey: m[1] ?? null, literal: m[2] ?? null, link: m[3] })
  }
  return items
})()

/** Существующие пути роутера как есть (с параметрами и без). */
const routePaths = flat.map(r => r.path).filter(Boolean)

/** Убирает ведущий и завершающий слэши. */
const normalizePath = (path) => path.replace(/^\/+/, '').replace(/\/+$/, '')

/**
 * Соответствие ссылки маршруту.
 *
 * Сверка идёт через сам vue-router: он корректно склеивает
 * '/group/learning/1' с шаблоном '/group/learning/:idEdit'. Ручное
 * сравнение строк здесь ошибается — параметр может стоять в любом
 * сегменте пути.
 */
const makeResolver = () => {
  const router = createRouter({ history: createMemoryHistory(), routes: routes || [] })
  return link => router.resolve(link).matched.length > 0
}

const matchesRoute = makeResolver()

describe('боковое меню: ссылки ведут на существующие маршруты', () => {
  it('пункты меню удалось разобрать из исходника', () => {
    // Если разбор сломается, тесты ниже станут ложнозелёными.
    expect(menuItems.length).toBeGreaterThan(10)
  })

  it('каждая ссылка меню соответствует маршруту роутера', () => {
    const broken = menuItems
      .filter(item => !matchesRoute(item.link))
      .map(item => `${item.link} (${item.i18nKey || item.literal})`)

    expect(broken, `ссылки без маршрута: ${broken.join(', ')}`).toEqual([])
  })

  it('нет пункта на /user/learning — маршрута такого нет', () => {
    const links = menuItems.map(i => i.link)
    expect(links).not.toContain('/user/learning')
  })

  it('у каждого пункта есть подпись', () => {
    const empty = menuItems.filter(i => !i.i18nKey && !i.literal).map(i => i.link)
    expect(empty, `пункты без подписи: ${empty.join(', ')}`).toEqual([])
  })
})

describe('боковое меню: подписи уникальны (регресс дубля id в Vuetify)', () => {
  it('нет двух пунктов с одинаковой подписью', () => {
    const resolved = menuItems.map(i => ({
      link: i.link,
      title: i.i18nKey
        ? ru.app.menu[i.i18nKey.split('.').pop()]
        : i.literal,
    }))

    const seen = new Map()
    const duplicates = []
    for (const item of resolved) {
      if (seen.has(item.title)) {
        duplicates.push(`«${item.title}»: ${seen.get(item.title)} и ${item.link}`)
      } else {
        seen.set(item.title, item.link)
      }
    }

    expect(duplicates, `дубли подписей: ${duplicates.join('; ')}`).toEqual([])
  })

  it('все подписи непустые после подстановки переводов', () => {
    for (const item of menuItems) {
      if (!item.i18nKey) continue
      const key = item.i18nKey.split('.').pop()
      expect(ru.app.menu[key], `нет перевода ${item.i18nKey}`).toBeTruthy()
      expect(en.app.menu[key], `нет перевода ${item.i18nKey} в en`).toBeTruthy()
    }
  })

  it('в ru и en одинаковый набор ключей меню', () => {
    expect(Object.keys(ru.app.menu).sort()).toEqual(Object.keys(en.app.menu).sort())
  })
})

describe('боковое меню: заголовок группы не дублирует подпись пункта', () => {
  /**
   * Регресс: заголовок аккордеона был захардкожен как «Пользователи» —
   * так же назывался пункт /user/list. Vuetify строит id узла из
   * заголовка, поэтому в консоли было «Multiple nodes with the same ID».
   */
  const groupTitleKey = (() => {
    // Блок аккордеона: <v-list-group value="true"> ... v-slot:activator ...
    const group = menuSource.slice(menuSource.indexOf('<v-list-group'))
    const activator = group.slice(0, group.indexOf('</v-list-group>'))
    const m = activator.match(/:title="\$t\('([^']+)'\)/)
    return m ? m[1] : null
  })()

  it('заголовок группы берётся из i18n, а не из литерала', () => {
    expect(groupTitleKey, 'заголовок группы должен быть ключом перевода').toBeTruthy()
    expect(ru.app.menu[groupTitleKey.split('.').pop()]).toBeTruthy()
    expect(en.app.menu[groupTitleKey.split('.').pop()]).toBeTruthy()
  })

  it('заголовок группы отличается от подписей всех пунктов меню', () => {
    const groupTitle = ru.app.menu[groupTitleKey.split('.').pop()].trim()
    const itemTitles = menuItems.map(i =>
      (i.i18nKey ? ru.app.menu[i.i18nKey.split('.').pop()] : i.literal).trim()
    )

    expect(itemTitles).not.toContain(groupTitle)
  })

  it('в исходнике нет захардкоженного заголовка группы', () => {
    expect(menuSource).not.toMatch(/v-list-group[\s\S]{0,600}title="Пользователи"/)
  })
})

describe('боковое меню: разрешение маршрутов роутером', () => {
  it('каждая ссылка меню резолвится роутером без исключений', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: routes || [] })
    const unresolved = []

    for (const item of menuItems) {
      try {
        router.resolve(item.link)
      } catch (error) {
        unresolved.push(`${item.link}: ${error.message}`)
      }
    }

    expect(unresolved, unresolved.join('; ')).toEqual([])
  })

  it('путь из меню попадает в компонент, а не в 404', () => {
    const router = createRouter({ history: createMemoryHistory(), routes: routes || [] })
    const learning = menuItems.find(i => i.link.startsWith('/group/learning'))

    expect(learning, 'в меню должен быть пункт учебного плана').toBeTruthy()
    expect(router.resolve(learning.link).matched.length).toBeGreaterThan(0)
  })
})