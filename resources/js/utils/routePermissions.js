import routes from '../Router/routes'

/**
 * Индекс «путь маршрута → требуемые права».
 *
 * Единственный источник требований к пунктам меню и профиля: поле
 * `meta.permission` в Router/routes.js. Права не дублируются в конфиге
 * навигации — иначе меню и страницы разошлись бы (см. LeftSideMenu).
 */

/** Рекурсивный обход дерева маршрутов. */
const walk = (list, index) => {
  ;(list || []).forEach((route) => {
    if (route.path) {
      index.set(route.path, Array.isArray(route.meta?.permission) ? route.meta.permission : [])
    }
    if (route.children) {
      walk(route.children, index)
    }
  })
}

/** Приводит ссылку к виду пути маршрута: без хэша и query. */
export const toRoutePath = (link) => String(link || '').split('#').pop().split('?')[0] || '/'

/** Путь без параметров: '/group/learning/:idEdit?' -> '/group/learning'. */
const basePath = (routePath) => routePath.replace(/:[^/]+\??/g, '').replace(/\/$/, '')

/**
 * Индекс требований.
 *
 * Строится один раз и переиспользуется: пунктов и ссылок десятки, а
 * обход маршрутов на каждый пункт — лишняя работа при каждом рендере.
 */
export const routePermissionIndex = () => {
  const index = new Map()
  walk(routes, index)
  return index
}

/**
 * Требования для ссылки.
 *
 * Сначала точное совпадение, иначе — самый длинный подходящий префикс:
 * пункт «/group/learning» ведёт на маршрут '/group/learning/:idEdit?'.
 * Без префикса пункт считался бы открытым для всех, потому что путь с
 * параметром не совпадал с индексом.
 *
 * @param {Map<string,string[]>} index
 * @param {string} link
 * @returns {string[]}
 */
export const requiredPermissions = (index, link) => {
  const path = toRoutePath(link)
  const exact = index.get(path)

  if (exact) {
    return exact
  }

  let best = null

  for (const [routePath, required] of index) {
    const base = basePath(routePath)

    if (base && path.startsWith(base) && (best === null || base.length > best.length)) {
      best = base
      index.set(path, required)
    }
  }

  return best === null ? [] : index.get(path)
}
