// @ts-check
/**
 * Бланк сертификата (Playwright).
 *
 * Проверяет то, что видно только в браузере:
 *  - сотрудник с правом открывает страницу настроек бланка;
 *  - предпросмотр обновляется сразу при вводе, до сохранения;
 *  - после сохранения значения переживают перезагрузку;
 *  - обучаемому пункт меню не показывается, а прямой заход даёт отказ.
 *
 * Настройки бланка — общие для всех, поэтому тест обязан вернуть
 * прежние значения: иначе он оставил бы после себя логотип и подпись
 * следующим прогонам и всем, кто откроет реальный сертификат.
 */
import { test, expect } from '@playwright/test'

const BASE = process.env.APP_URL || 'http://127.0.0.1:8000'
const HASH = process.env.APP_HASH_URL || BASE + '/#'
const INSTRUCTOR = { fio: 'Иванов Иван Иванович', password: '123' }
const TRAINEE = { fio: 'Петров Иван Иванович', password: '123' }

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

test.describe('Бланк сертификата', () => {
  test('настройки сохраняются и переживают перезагрузку', async ({ page }) => {
    const errors = []
    page.on('pageerror', e => errors.push(e.message.split('\n')[0]))

    const token = await login(page, INSTRUCTOR)

    let previous = {}
    try {
      const read = await page.request.get(BASE + '/api/certificates/settings', {
        headers: { Authorization: 'Bearer ' + token },
      })
      previous = (await read.json()).data ?? {}

      await page.goto(`${HASH}/certificates/settings`, { waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1600)

      await expect(
        page.getByRole('heading', { name: 'Бланк сертификата', exact: true }).first(),
        'заголовок страницы настроек'
      ).toBeVisible()

      // По подписи, а не по порядку полей: добавление или перестановка
      // полей не должна ломать тест.
      const organization = page.getByLabel('Организация', { exact: true })
      const signatory = page.getByLabel('Подписант', { exact: true })

      await organization.fill('E2E Учебный центр')
      await signatory.fill('E2E Подписант')

      // Предпросмотр должен показать новое значение ДО сохранения:
      // иначе правку нельзя было бы увидеть, не отправляя её.
      await expect(
        page.locator('.cert-sheet--preview'),
        'предпросмотр показывает введённую организацию'
      ).toContainText('E2E Учебный центр')

      await page.getByRole('button', { name: 'Сохранить бланк' }).click()
      await page.waitForTimeout(1200)

      await page.reload({ waitUntil: 'domcontentloaded' })
      await page.waitForTimeout(1600)

      await expect(
        organization,
        'организация пережила перезагрузку'
      ).toHaveValue('E2E Учебный центр')

      expect(errors, 'сценарий не должен давать ошибок').toEqual([])
    } finally {
      // Возвращаем бланк как было: он общий, и следующий прогон
      // (и живой сертификат) не должен печатать «E2E Учебный центр».
      await page.request
        .put(BASE + '/api/certificates/settings', {
          headers: { Authorization: 'Bearer ' + token },
          data: {
            organization: previous.organization ?? null,
            signatory_name: previous.signatory_name ?? null,
            signatory_title: previous.signatory_title ?? null,
            footer_note: previous.footer_note ?? null,
            ...(previous.logo_url ? {} : { remove_logo: true }),
          },
        })
        .catch(() => null)
    }
  })

  test('обучаемому пункт меню не показывается, а прямой заход закрыт', async ({ page }) => {
    await login(page, TRAINEE)

    await page.goto(`${HASH}/dashboard`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    await expect(
      page.locator('.nav a[href*="/certificates/settings"]'),
      'пункта меню у обучаемого быть не должно'
    ).toHaveCount(0)

    await page.goto(`${HASH}/certificates/settings`, { waitUntil: 'domcontentloaded' })
    await page.waitForTimeout(1500)

    await expect(
      page.locator('[data-test="cert-settings-logo-pick"]'),
      'страница настроек не должна открываться обучаемому'
    ).toHaveCount(0)
  })
})
