// @vitest-environment jsdom
/**
 * Подтверждение необратимых действий.
 *
 * Регресс на кнопку «Очистить базу данных» в AddClass.vue: она стирала
 * 13 таблиц контента одним кликом, без диалога и без возможности
 * отмены. Теперь кнопка только открывает ConfirmDialog, а запрос уходит
 * после подтверждения.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest'
import { readFileSync } from 'node:fs'

const source = readFileSync('resources/js/Pages/Course/AddClass.vue', 'utf8')

describe('AddClass: очистка базы только после подтверждения', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('кнопка очистки открывает диалог, а не стирает сразу', () => {
    // Прямой вызов clearDatabase из разметки — это и был баг.
    expect(source).not.toMatch(/@click="clearDatabase"/)
    expect(source).toMatch(/@click="confirmClear = true"/)
  })

  it('диалог подтверждения подключён', () => {
    expect(source).toMatch(/<ConfirmDialog/)
    expect(source).toMatch(/v-model="confirmClear"/)
    expect(source).toMatch(/@confirm="clearDatabase"/)
  })

  it('запрос на очистку уходит только из подтверждения', () => {
    // Метод остаётся, но он вызывается только из @confirm диалога.
    const methodStart = source.indexOf('async clearDatabase()')
    expect(methodStart).toBeGreaterThan(-1)

    // Внутри метода нет обращения к confirmClear = true: очистка не
    // должна сама открывать диалог (иначе он не закрылся бы).
    const body = source.slice(methodStart, methodStart + 1600)
    expect(body).not.toMatch(/confirmClear = true/)
  })

  it('при ошибке диалог остаётся открытым', () => {
    const methodStart = source.indexOf('async clearDatabase()')
    const body = source.slice(methodStart, methodStart + 1600)

    // Закрываем ПОСЛЕ успешного ответа, иначе при ошибке повторить
    // было бы нечем.
    expect(body.indexOf('confirmClear = false')).toBeGreaterThan(body.indexOf('/api/clear-database'))
  })
})
