/**
 * Видимость пунктов навигации — одно место для меню и палитры команд.
 *
 * Зачем отдельный файл: правило «пункт виден, если хватает прав из
 * meta.permission маршрута и он подходит по роли» применяется сразу в
 * двух местах — боковое меню и палитра Ctrl/Cmd+K. Дублировать его
 * значит через правку разойтись: палитра продолжит предлагать раздел,
 * который меню уже скрыло по правам, и наоборот.
 */

import { requiredPermissions } from './routePermissions'

/**
 * Виден ли пункт по правам.
 *
 * @param {Map<string, string[]>} index  индекс прав по ссылкам
 *   (buildRoutePermissionIndex), а не функция: здесь мы его читаем,
 *   а не вычисляем заново.
 * @param {(link: string) => string[]} requiredBy  сборщик требований
 *   для ссылки; по умолчанию берётся из routePermissions
 */
export function allowsLink(link, index, can, requiredBy) {
  if (typeof can !== 'function') {
    // Без геттера can судить не о чем: показываем пункт, а не прячем
    // половину меню из-за неготового стора.
    return true
  }

  const resolve = typeof requiredBy === 'function'
    ? requiredBy
    : (target) => requiredPermissions(index, target)

  const required = resolve(link)

  if (!required || required.length === 0) {
    return true
  }

  return can(...required)
}

/**
 * Подходит ли пункт по роли.
 *
 * onlyFor понимает ровно два значения: 'manager' и всё остальное.
 * Любой другой список молча проходил бы как «не manager», то есть
 * показывал бы пункт всем — включая тех, кому он не адресован.
 */
export function fitsRole(item, isManager) {
  if (!item || !item.onlyFor) return true

  return item.onlyFor === 'manager' ? isManager : !isManager
}

/**
 * Разрешённые и переведённые пункты меню.
 *
 * @param {{key: string, titleKey: string, link: string, icon?: string}[]} items
 * @param {{can: Function, isManager: boolean, t: Function, index?: Map, requiredBy?: Function}} ctx
 */
export function visibleItems(items, ctx) {
  const { can, isManager, t, requiredBy } = ctx
  const index = ctx.index

  return (items || [])
    .filter((item) => allowsLink(item.link, index, can, requiredBy))
    .filter((item) => fitsRole(item, isManager))
    .map((item) => ({
      ...item,
      // titleKey без перевода показывался в интерфейсе как «app.menu.X»:
      // ключ полезен разработчику и бесполезен человеку.
      title: t(item.titleKey),
    }))
}

/**
 * Видимые секции с заголовками и флагом «показывать заголовок».
 *
 * Заголовок скрывается, когда секция одна: иначе он занимает строку и
 * визуально приклеивает к себе пункт, который к нему отношения не
 * имеет.
 */
export function visibleSections(sections, ctx) {
  const list = (sections || [])
    .map((section) => ({
      key: section.key,
      title: ctx.t(`app.nav.${section.key}`),
      items: visibleItems(section.items, ctx),
    }))
    .filter((section) => section.items.length > 0)

  const showHeaders = list.length > 1

  return list.map((section) => ({ ...section, showHeader: showHeaders }))
}
