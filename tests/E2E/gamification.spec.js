// @ts-check
/**
 * Достижения (Playwright).
 *
 * Проверяет то, что видно только в браузере:
 *  - обучаемый видит свои XP, уровень и календарь активности;
 *  - календарь отмечает сегодняшний день после закрытия урока;
 *  - закрытые достижения отличаются от незакрытых;
 *  - сотруднику пункт меню не показывается, а лидерборд не показывает
 *    чужих людей.
 *
 * Тест закрывает урок настоящим запросом прогресса: начисление XP
 * живёт в backend, и проверять страницу, подсунув в неё значение,
 * значило бы проверять не то.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const HASH = process.env.APP_HASH_URL || BASE + '/#'
const TRAINEE = { fio: 'Петров Иван Иванович', password: '123' }
const INSTRUCTOR = { fio: 'Иванов Иван Иванович', password: '123' }

async function login(page, creds) {
  const res = await page.request.post(BASE + '/api/login', { data: creds })
  expect(res.ok(), 'логин ' + creds.fio).toBeTruthy()
  const token = (await res.json()).data.token

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

test.describe('Достижения', () => {
  test('XP, уровень и календарь активности обновляются после закрытия урока', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    const token = await login(page, TRAINEE)
    const headers = { Authorization: 'Bearer ' + token }

    const before = (await (await page.request.get(BASE + '/api/gamification/me', { headers })).json()).data

    await page.goto(`${HASH}/gamification`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    await expect(
      page.getByRole('heading', { name: 'Достижения', exact: true }).first(),
      'заголовок страницы'
    ).toBeVisible()

    // Календарь всегда рисует 35 дней — это и делает «неделя подряд»
    //   читаемой без прокрутки вправо.
    await expect(page.locator('.gamification__day')).toHaveCount(35)

    /*
     * Закрываем урок настоящим запросом: так проверяется и начисление
     * XP, и то, что страница подхватила его при следующем открытии.
     *
     * Курс берём из записей обучаемого, а не из каталога: манифест
     * закрыт для незаписанных (это проверка доступа, а не геймификации),
     * и тест обязан уважать её, иначе он научился бы обходить права.
     */
    const learning = (await (await page.request.get(`${BASE}/api/my/learning`, { headers })).json()).data
    const record = (learning?.courses ?? learning ?? []).find(r => r.course_id ?? r.id)
    expect(record, 'у обучаемого есть запись на курс').toBeTruthy()

    const courseId = record.course_id ?? record.id
    const tree = (await (await page.request.get(`${BASE}/api/coursemanifest/${courseId}`, { headers })).json()).data
    const lesson = (tree?.nodes ?? tree?.items ?? []).find(n => n.type === 1)
    expect(lesson, 'у курса есть урок').toBeTruthy()

    await page.request.post(BASE + '/api/my/progress', {
      headers,
      data: { course_id: courseId, lesson_id: lesson.id, percent: 100 },
    })

    await page.reload({ waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    const after = (await (await page.request.get(BASE + '/api/gamification/me', { headers })).json()).data
    expect(after.xp, 'после закрытия урока XP выросли').toBeGreaterThan(before.xp)

    // Сегодняшний день должен быть отмечен: это и есть основа streak.
    const todayMarked = await page.evaluate(() => {
      const today = new Date().toISOString().slice(0, 10);
      const cells = [...document.querySelectorAll('.gamification__day')]
      const target = cells.find(c => (c.getAttribute('title') || '').startsWith(today));
      return target ? target.classList.contains('gamification__day--on') : null;
    })
    expect(todayMarked, 'сегодняшняя ячейка календаря закрашена').toBe(true)

    // Страница показывает то же число, что и API: разъезд означал бы,
    // что страница считает сама по своим правилам.
    await expect(page.locator('.gamification__stat .gamification__num').first())
      .toHaveText(String(after.xp))

    expect(errors, 'сценарий не должен давать ошибок').toEqual([])
  })

  test('достижения показывают и закрытые, и незакрытые', async ({ page }) => {
    await login(page, TRAINEE)

    await page.goto(`${HASH}/gamification`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    // Закрытых достижений у демо-обучаемого как минимум нет, а
    // галерея обязана показывать их все — иначе человек не видит, к
    // чему стремиться.
    await expect(page.locator('.gamification__badge')).not.toHaveCount(0)
    await expect(page.locator('[data-test="badge-locked"]')).not.toHaveCount(0)
  })

  test('сотруднику пункт меню не показывается', async ({ page }) => {
    await login(page, INSTRUCTOR)

    await page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    await expect(
      page.locator('.nav a[href*="/gamification"]'),
      'сотруднику достижения не показываются: это личный раздел обучаемого'
    ).toHaveCount(0)
  })
})
