import { chromium } from 'playwright'
const ok=(n,c,d='')=>console.log(`${c?'OK  ':'FAIL'} ${n}${d?' — '+d:''}`)
const b = await chromium.launch({ executablePath: '/usr/bin/google-chrome', args: ['--no-sandbox','--disable-dev-shm-usage'] })
const p = await b.newPage({ viewport: { width: 390, height: 780 } })
await p.goto('http://127.0.0.1:8080/#/login', { waitUntil: 'networkidle' })
await p.fill('#userLogin','Фурсов Андрей Ильич'); await p.fill('#password','123')
await p.click('button[type="submit"]'); await p.waitForTimeout(3500)
console.log('URL:', p.url())
const nav = p.locator('.u-bottom-nav')
ok('нижняя навигация обучаемого показана', await nav.count() > 0)
if (await nav.count()) {
  const items = await nav.locator('.u-bottom-nav__item').evaluateAll(els => els.map(e => e.innerText.trim()))
  console.log('    пункты:', items)
  ok('пунктов 4', items.length === 4, String(items.length))
  const box = await nav.first().boundingBox()
  console.log('    позиция:', box ? `y=${Math.round(box.y)} из 780 (прижата к низу: ${box.y > 600})` : '—')
  await p.screenshot({ path: '/tmp/trainee-mobile.png' })
}
await b.close()
