// @ts-check
import { test, expect } from '@playwright/test'

/**
 * Страница материала курса (/courses/itemmani) и запись групп на курсы
 * (/group/learning).
 *
 * Проверяются регрессии, найденные при разборе этих страниц:
 *
 *  1. КРИТИЧНО. Высота кадра задавалась инлайновым onload, а Vue
 *     затирал её пустым :style при каждом ре-рендере — материал
 *     схлопывался до пустого кадра и оставался таким.
 *  2. Кнопка «вверх» нависала над материалом и схлопывала его.
 *  3. Подсветка наведения делалась через document.getElementById на
 *     каждом движении мыши (182 узла).
 *  4. Шапка с иконками ломалась: width:130px на трёх иконках в колонке
 *     4/12 плюс margin-bottom:-31px у разделителя.
 *  5. На странице записи групп не было выбора группы, ошибки валидации
 *     выводились внутри HTML-комментария (пустые красные блоки), а
 *     «Сохранить» молча ничего не делал.
 */

const BASE = 'http://127.0.0.1:8080'
const ADMIN = { fio: 'Администратор', password: '123' }

/** @param {import('@playwright/test').Page} page */
async function auth(page, creds = ADMIN) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  const body = await res.json()
  const token = body?.data?.token
  expect(token, 'токен администратора').toBeTruthy()

  const me = await page.request.get(BASE + '/api/v1/me', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const user = (await me.json())?.data?.user ?? null

  await page.addInitScript(
    ([t, u]) => {
      localStorage.setItem('token', t)
      localStorage.setItem('user', u)
    },
    [token, JSON.stringify(user)]
  )
  return token
}

/** Находит курс с хотя бы одним модулем (type 3). */
async function findCourseWithModules(page, token) {
  const res = await page.request.get(BASE + '/api/courses', {
    headers: { Authorization: 'Bearer ' + token },
  })
  const courses = (await res.json())?.data ?? []

  for (const course of courses) {
    const mani = await page.request.get(BASE + '/api/course?course_id=' + course.id, {
      headers: { Authorization: 'Bearer ' + token },
    })
    const auk = (await mani.json())?.data?.[0]?.aukstructures ?? []
    if (auk.filter((a) => a.type === 3).length > 1) return course
  }
  return null
}

test.describe('страница материала курса', () => {
  test('материал не схлопывается и прокручивается', async ({ page }) => {
    const errors = []
    page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]))

    const token = await auth(page)
    const course = await findCourseWithModules(page, token)
    test.skip(!course, 'нет курса с модулями')

    await page.goto(`${BASE}/#/courses/itemmani?idEdit=${course.id}`, {
      waitUntil: 'domcontentloaded',
    })
    await page.waitForSelector('.cm-toolbar')
    await page.waitForTimeout(5000)

    const before = await page.evaluate(() => {
      const el = document.querySelector('.cm-content')
      return {
        scrollHeight: el?.scrollHeight ?? 0,
        clientHeight: el?.clientHeight ?? 0,
        frameHeight:
          document.querySelector('iframe.hello')?.getBoundingClientRect().height ?? 0,
      }
    })

    // КРИТИЧНО. Материал не должен схлопнуться в пустой кадр (~150px),
    // и контейнер должен прокручиваться, а не растягиваться на весь
    // документ (иначе прокручивается вся страница, а не материал).
    expect(before.frameHeight, 'материал отрисован').toBeGreaterThan(200)
    expect(before.scrollHeight, 'контейнер прокручиваемый').toBeGreaterThan(
      before.clientHeight
    )

    // КРИТИЧНО. Кнопка «вверх» раньше рендерилась через v-if и при
    // своём появлении пересоздавала iframe: документ материала
    // становился пустым, высота падала, прокрутка сбрасывалась в ноль.
    // Прокрутка — как раз то событие, которым кнопка появляется.
    await page.evaluate(() => {
      document.querySelector('.cm-content').scrollTop = 800
    })
    await page.waitForTimeout(800)

    const scrolled = await page.evaluate(() => {
      const el = document.querySelector('.cm-content')
      return {
        scrollTop: el?.scrollTop ?? 0,
        frameHeight:
          document.querySelector('iframe.hello')?.getBoundingClientRect().height ?? 0,
      }
    })

    expect(scrolled.scrollTop, 'контейнер прокрутился').toBeGreaterThan(100)
    expect(scrolled.frameHeight, 'после прокрутки материал не схлопнулся').toBeGreaterThan(200)
    await expect(page.locator('.cm-scroll-top'), 'кнопка «вверх» появилась').toBeVisible()

    await page.locator('.cm-scroll-top').click()
    await page.waitForTimeout(1200)

    const backToTop = await page.evaluate(
      () => document.querySelector('.cm-content')?.scrollTop ?? -1
    )
    expect(backToTop, 'кнопка «вверх» вернула в начало').toBeLessThan(50)

    // Перерисовка при смене материала: новый документ должен
    // отрисоваться, кнопка «вверх» — скрыться (новое открытие сверху).
    await page.locator('.auk-node__title--module').nth(1).click()
    await page.waitForTimeout(2500)

    const afterSwitch = await page.evaluate(
      () => document.querySelector('iframe.hello')?.getBoundingClientRect().height ?? 0
    )
    expect(afterSwitch, 'материал после переключения отрисован').toBeGreaterThan(200)
    await expect(page.locator('.cm-scroll-top'), 'кнопка скрыта наверху страницы').toBeHidden()

    expect(errors, `ошибки в консоли: ${errors.join('; ')}`).toEqual([])
  })

  test('шапка не ломается и не переполняется', async ({ page }) => {
    const token = await auth(page)
    const course = await findCourseWithModules(page, token)
    test.skip(!course, 'нет курса с модулями')

    for (const width of [1280, 1600]) {
      await page.setViewportSize({ width, height: 900 })
      await page.goto(`${BASE}/#/courses/itemmani?idEdit=${course.id}`, {
        waitUntil: 'domcontentloaded',
      })
      await page.waitForSelector('.cm-toolbar')
      await page.waitForTimeout(2500)

      const bar = await page.evaluate(() => {
        const el = document.querySelector('.cm-toolbar')
        const cs = getComputedStyle(el)
        return {
          overflowX: el.scrollWidth > el.clientWidth + 1,
          display: cs.display,
          tools: el.querySelectorAll('.cm-tool').length,
          marginBottom: cs.marginBottom,
        }
      })

      expect(bar.display, `панель — flex при ${width}px`).toBe('flex')
      expect(bar.tools, 'все четыре инструмента на месте').toBe(4)
      expect(bar.overflowX, `панель не переполняется при ${width}px`).toBe(false)
      // Отрицательный отступ разваливал шапку.
      expect(parseFloat(bar.marginBottom), 'нет отрицательного отступа').toBeGreaterThanOrEqual(0)
    }
  })

  test('наведение подсвечивает пункт, посещённый остаётся серым', async ({ page }) => {
    const token = await auth(page)
    const course = await findCourseWithModules(page, token)
    test.skip(!course, 'нет курса с модулями')

    await page.goto(`${BASE}/#/courses/itemmani?idEdit=${course.id}`, {
      waitUntil: 'domcontentloaded',
    })
    await page.waitForSelector('.cm-toolbar')
    await page.waitForTimeout(4000)

    const modules = page.locator('.auk-node__title--module')
    await expect(modules.first()).toBeVisible()

    const base = await modules
      .nth(2)
      .evaluate((el) => getComputedStyle(el).backgroundColor)

    await modules.nth(2).hover()
    await page.waitForTimeout(400)
    const hovered = await modules
      .nth(2)
      .evaluate((el) => getComputedStyle(el).backgroundColor)

    expect(hovered, 'при наведении есть подсветка').not.toBe(base)
    expect(hovered).toBe('rgb(232, 238, 247)')

    // Открытый модуль помечается посещённым и подсвечивается серым.
    await modules.nth(2).click()
    await page.waitForTimeout(2500)

    // Открытый модуль — это ещё и текущий, а текущий красится синим
    // поверх посещённого. Чтобы проверить именно серый, нужно увести
    // «текущий» на другой пункт: тогда прежний останется посещённым,
    // но перестанет быть активным.
    await modules.nth(3).click()
    await page.waitForTimeout(2500)
    await page.mouse.move(5, 5)
    await page.waitForTimeout(400)

    const visited = await page.evaluate(() => {
      const el = document.querySelector('.auk-node__title--visited')
      return el ? getComputedStyle(el).backgroundColor : null
    })
    expect(visited, 'посещённый, но не текущий пункт подсвечен серым').toBe(
      'rgb(236, 236, 236)'
    )

    // Под наведением посещённый пункт всё равно должен отличаться от
    // обычного серого: это явное правило, а не побочный эффект.
    await page.locator('.auk-node__title--visited').first().hover()
    await page.waitForTimeout(400)
    const visitedHovered = await page.evaluate(() => {
      const el = document.querySelector('.auk-node__title--visited')
      return el ? getComputedStyle(el).backgroundColor : null
    })
    expect(visitedHovered, 'посещённый под курсором подсвечивается').toBe('rgb(223, 231, 242)')
    await page.mouse.move(5, 5)

    const stored = await page.evaluate(
      (id) => localStorage.getItem(`course-manifest-visited:${id}`),
      course.id
    )
    expect(stored, 'прогресс сохраняется').toBeTruthy()
  })

  test('поиск и избранное не гасят дерево', async ({ page }) => {
    const token = await auth(page)
    const course = await findCourseWithModules(page, token)
    test.skip(!course, 'нет курса с модулями')

    await page.goto(`${BASE}/#/courses/itemmani?idEdit=${course.id}`, {
      waitUntil: 'domcontentloaded',
    })
    await page.waitForSelector('.cm-toolbar')
    await page.waitForTimeout(4000)

    const nodesBefore = await page.locator('.auk-node__title').count()

    await page.getByRole('button', { name: 'Поиск', exact: true }).click()
    await page.waitForTimeout(800)
    expect(await page.locator('.auk-node__title').count(), 'дерево осталось на месте').toBe(
      nodesBefore
    )

    await page.getByRole('button', { name: 'Избранное', exact: true }).click()
    await page.waitForTimeout(1000)
    expect(await page.locator('.auk-node__title').count(), 'дерево не пропало').toBe(nodesBefore)

    // Пустое избранное должно объяснять себя, а не быть пустым списком.
    const emptyHint = await page.locator('.cm-favorites__empty').count()
    const items = await page.locator('.cm-favorites__item').count()
    expect(emptyHint + items, 'избранное что-то показывает').toBeGreaterThan(0)
  })

  test('избранное: добавить и убрать', async ({ page }) => {
    const token = await auth(page)
    const course = await findCourseWithModules(page, token)
    test.skip(!course, 'нет курса с модулями')

    // Чистим всё: иначе записи от прошлых прогонов ломают проверку
    // ожидаемого количества.
    const stale = await page.request.get(BASE + '/api/favorites/', {
      headers: { Authorization: 'Bearer ' + token },
    })
    for (const item of (await stale.json())?.data?.favorites ?? []) {
      await page.request.delete(BASE + '/api/favorites/' + item.course_id, {
        headers: { Authorization: 'Bearer ' + token },
      })
    }

    await page.goto(`${BASE}/#/courses/itemmani?idEdit=${course.id}`, {
      waitUntil: 'domcontentloaded',
    })
    await page.waitForSelector('.cm-toolbar')
    await page.waitForTimeout(4000)

    await page.locator('.auk-node__title--module').nth(4).click()
    await page.waitForTimeout(2500)

    await page.getByRole('button', { name: 'В избранное', exact: true }).click()
    await page.waitForTimeout(2000)

    await page.getByRole('button', { name: 'Избранное', exact: true }).click()
    await page.waitForTimeout(1000)
    await expect(page.locator('.cm-favorites__item')).toHaveCount(1)

    await page.locator('.cm-favorites__item button').first().click()
    await page.waitForTimeout(2000)
    await expect(page.locator('.cm-favorites__item')).toHaveCount(0)

    // Убираем за собой.
    const list = await page.request.get(BASE + '/api/favorites/', {
      headers: { Authorization: 'Bearer ' + token },
    })
    const remaining = (await list.json())?.data?.favorites ?? []
    for (const item of remaining) {
      await page.request.delete(BASE + '/api/favorites/' + item.course_id, {
        headers: { Authorization: 'Bearer ' + token },
      })
    }
  })
})

test.describe('запись групп на курсы', () => {
  /** Заполняет первые select'ы страницы. */
  const pick = async (page, index, option = 0) => {
    const select = page.locator('.v-select').nth(index)
    await select.click()
    await page.waitForTimeout(600)
    const options = await page.$$('.v-overlay .v-list-item')
    if (!options.length) return 0
    await options[Math.min(option, options.length - 1)].click()
    await page.waitForTimeout(1800)
    return options.length
  }

  test('есть выбор группы, а пункт меню не привязан к группе №1', async ({ page }) => {
    await auth(page)

    await page.goto(`${BASE}/#/group/learning`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(4000)

    const labels = await page.evaluate(() =>
      [...document.querySelectorAll('.v-select')].map((s) =>
        s.querySelector('.v-label')?.textContent?.trim()
      )
    )
    expect(labels, 'есть поле выбора группы').toContain('Группа')

    const options = await pick(page, 0)
    expect(options, 'группы доступны для выбора').toBeGreaterThan(0)

    // Пункт меню ведёт на страницу без id — группу выбирают в форме.
    await page.goto(`${BASE}/#/courses/list`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(3500)
    const href = await page.evaluate(() => {
      const link = [...document.querySelectorAll('.v-navigation-drawer a')].find((a) =>
        (a.getAttribute('href') ?? '').includes('/group/learning')
      )
      return link?.getAttribute('href') ?? null
    })
    expect(href, 'пункт «Учебный план» есть').toBeTruthy()
    expect(href, 'в пункте меню не зашита группа №1').toBe('#/group/learning')
  })

  test('ошибки валидации видны текстом, а не пустыми блоками', async ({ page }) => {
    await auth(page)
    await page.goto(`${BASE}/#/group/learning`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(4000)

    await page.getByRole('button', { name: 'Сохранить' }).click()
    await page.waitForTimeout(1500)

    const texts = await page.evaluate(() =>
      [...document.querySelectorAll('.v-alert, .v-messages__message')]
        .map((n) => n.textContent?.trim())
        .filter((t) => t && t.length > 3)
    )
    expect(texts.length, 'показаны сообщения об ошибках').toBeGreaterThan(0)
    // Регресс: интерполяция была внутри HTML-комментария, пользователь
    // видел пустые красные блоки без единого слова.
    expect(texts.some((t) => t.includes('Выберите')), 'ошибки читаемы').toBe(true)

    // Незаполненная форма не должна уходить на сервер.
    expect(page.url()).toContain('/group/learning')
  })

  test('дерево курсов наполняется и запись сохраняется', async ({ page }) => {
    const token = await auth(page)
    await page.goto(`${BASE}/#/group/learning`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(4000)

    expect(await pick(page, 0), 'выбрана группа').toBeGreaterThan(0)
    expect(await pick(page, 1), 'выбран класс').toBeGreaterThan(0)
    expect(await pick(page, 2), 'выбрана категория').toBeGreaterThan(0)

    // Дерево курсов должно раскрыться.
    await page.locator('.vue-treeselect__control').click()
    await page.waitForTimeout(1200)
    const labels = await page.locator('.vue-treeselect__label').count()
    expect(labels, 'дерево курсов построено').toBeGreaterThan(0)

    await page.keyboard.press('Escape')
    const dates = await page.$$('input[type="date"]')
    await dates[0].fill('2026-12-01')
    await dates[1].fill('2026-12-31')

    await page.locator('.vue-treeselect__control').click()
    await page.waitForTimeout(1000)
    const leaves = await page.locator('.vue-treeselect__label').all()
    await leaves[leaves.length - 1].click()
    await page.waitForTimeout(800)

    // Запоминаем id созданных записей, чтобы убрать именно свои.
    //
    // Раньше уборка проходила по всем группам подряд и удаляла ВСЕ записи
    // учебного плана — включая реальные записи обучаемого. Плюс проверка
    // брала «первую группу с записями», а это оказывалась группа с
    // чужими данными, и тест падал на study_from.
    const createdIds = []
    page.on('response', async (r) => {
      if (!r.url().includes('/api/group/learning') || r.status() !== 201) return
      const body = await r.json().catch(() => null)
      // Эндпоинт возвращает массив id созданных строк.
      for (const id of (body?.data ?? [])) {
        if (id != null) createdIds.push(id)
      }
    })

    await page.getByRole('button', { name: 'Сохранить' }).click()
    await page.waitForTimeout(3500)

    expect(createdIds.length, 'запись создана и вернула id').toBeGreaterThan(0)

    const groups = await page.request.get(BASE + '/api/groups', {
      headers: { Authorization: 'Bearer ' + token },
    })
    const list = (await groups.json())?.data ?? []

    const rows = list.flatMap((g) => (g.group2learnings ?? []).map((r) => ({ ...r, group_id: g.id })))
    const mine = rows.filter((r) => createdIds.includes(r.id))

    expect(mine.length, 'запись видна в группе').toBe(createdIds.length)
    expect(mine[0]?.course_id, 'сохранён настоящий id курса').toBeTruthy()
    expect(mine[0]?.study_from).toBe('2026-12-01')
    expect(mine[0]?.study_to).toBe('2026-12-31')

    // Убираем за собой ТОЛЬКО свои записи.
    for (const id of createdIds) {
      await page.request.delete(`${BASE}/api/learning/${id}`, {
        headers: { Authorization: 'Bearer ' + token },
      })
    }
  })
})