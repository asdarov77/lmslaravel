import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join, relative, resolve } from 'node:path'
import * as vuetifyComponents from 'vuetify/components'

const here = dirname(fileURLToPath(import.meta.url))
const root = resolve(here, '../..')
const pagesDir = resolve(root, 'resources/js')

/**
 * Проект переведён на Vuetify 3, но в шаблонах остались компоненты
 * Vuetify 1/2. Каждый такой тег даёт в консоли
 * «[Vue warn]: Failed to resolve component: v-flex», а элемент просто
 * не отрисовывается — страница выглядит «пустой» без явной ошибки.
 */
const LEGACY_COMPONENTS = [
  // Сетка Vuetify 1/2 -> v-container/v-row/v-col в Vuetify 3.
  'v-flex',
  'v-grid-container',
  'v-grid-item',
  'v-item',
  'v-footer-absolute',
  // Списки: в Vuetify 3 переименованы или удалены.
  'v-list-item-content',
  'v-list-item-icon',
  'v-list-item-group',
  'v-subheader',
  'v-list-tile',
  // Компонентов с такими именами не существует ни в одной версии.
  'v-title',
]

const walk = (dir) => {
  const files = []
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) {
      files.push(...walk(full))
    } else if (full.endsWith('.vue')) {
      files.push(full)
    }
  }
  return files
}

const vueFiles = walk(pagesDir)

describe('шаблоны используют только компоненты Vuetify 3', () => {
  it('файлы .vue найдены', () => {
    expect(vueFiles.length).toBeGreaterThan(30)
  })

  it('нет компонентов Vuetify 1/2', () => {
    const offenders = []

    for (const file of vueFiles) {
      const source = readFileSync(file, 'utf8')
      for (const component of LEGACY_COMPONENTS) {
        // Открывающий или закрывающий тег: <v-flex, </v-flex>
        const re = new RegExp(`<\\/?${component}[\\s/>]`, 'i')
        if (re.test(source)) {
          offenders.push(`${relative(root, file)}: ${component}`)
        }
      }
    }

    expect(offenders, `компоненты Vuetify 1/2: ${offenders.join(', ')}`).toEqual([])
  })

  it('все v-* теги шаблонов реально существуют в Vuetify 3', () => {
    // Известные исключения: локальные компоненты проекта.
    const localComponents = new Set([
      'v-progress-circular',
      'v-btn-toggle',
      'v-progress-linear',
      'v-alert',
    ])
    const known = new Set(
      Object.keys(vuetifyComponents).map(n =>
        n
          .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
          .replace(/([A-Z]+)([A-Z][a-z])/g, '$1-$2')
          .toLowerCase()
      )
    )
    // Компоненты, регистрируемые глобально в resources/js/app.js.
    known.add('v-progress-circular')
    known.add('button-group')

    const unknown = new Set()

    for (const file of vueFiles) {
      const source = readFileSync(file, 'utf8')
      for (const m of source.matchAll(/<([a-z][a-z0-9]*(?:-[a-z0-9]+)+)/g)) {
        const tag = m[1]
        if (!tag.startsWith('v-')) continue
        if (LEGACY_COMPONENTS.includes(tag)) continue
        if (localComponents.has(tag)) continue
        if (!known.has(tag)) unknown.add(tag)
      }
    }

    // Пустой список обязателен: неизвестный тег в prod-сборке молча
    // превращается в пустой элемент.
    expect([...unknown], `неизвестные компоненты: ${[...unknown].join(', ')}`).toEqual([])
  })

  it('каждая пара открывающих/закрывающих тегов сбалансирована', () => {
    // v-flex -> v-col менялся массовой заменой; дисбаланс даёт
    // «Element is missing end tag» и битый DOM.
    const offenders = []

    for (const file of vueFiles) {
      // Закомментированные куски разметки (<!-- <v-sheet> -->) учитывать
      // нельзя: в шаблонах их много, они не участвуют в рендере.
      const source = readFileSync(file, 'utf8').replace(/<!--[\s\S]*?-->/g, '')

      // Значения атрибутов могут содержать «>»: в инлайновых обработчиках
      // вида @click="() => { ... }" стрелка ломает наивный разбор тегов.
      // Вырезаем их, чтобы увидеть только синтаксис разметки.
      const withoutAttrs = source.replace(/"[^"]*"|'[^']*'/g, '""')

      const counts = new Map()

      for (const m of withoutAttrs.matchAll(/<(\/?)(v-[a-z0-9-]+)([^>]*?)(\/?)>/g)) {
        const [, closing, tag, attrs, selfClose] = m
        const isVoid = /\/>$/.test(attrs) || selfClose === '/'
        if (isVoid) continue

        const current = counts.get(tag) ?? 0
        counts.set(tag, closing ? current - 1 : current + 1)
      }

      for (const [tag, count] of counts) {
        if (count !== 0) offenders.push(`${relative(root, file)}: ${tag} (${count})`)
      }
    }

    expect(offenders, `дисбаланс тегов: ${offenders.join(', ')}`).toEqual([])
  })
})