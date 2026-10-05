// @ts-check
/**
 * Витрина курсов и самостоятельная запись (Playwright).
 *
 * Проверяет полный пользовательский цикл: открыть витрину, записаться
 * на курс, увидеть, что материал открылся, и отписаться.
 *
 * Отдельно проверяется граница доступа: до записи материал курса
 * недоступен (403), после — открыт. Именно её появление витрины и
 * сделало необходимой: раньше /api/course/{id} висел на одном
 * auth:sanctum, и любой вошедний читал курс по идентификатору.
 *
 * Данные создаются и удаляются внутри теста.
 */
import { test, expect } from "@playwright/test";

const BASE = process.env.APP_URL || "http://127.0.0.1:8000";
const ADMIN = { fio: "Администратор", password: "123" };
const PASSWORD = "secret123";

const unique = () => `${Date.now()}${Math.floor(Math.random() * 1000)}`;

async function session(browser, creds) {
    const ctx = await browser.newContext({
        viewport: { width: 1440, height: 950 },
    });
    const page = await ctx.newPage();

    const res = await ctx.request.post(BASE + "/api/login", { data: creds });
    expect(res.ok(), "логин " + creds.fio).toBeTruthy();
    const token = (await res.json()).data.token;

    const me = await (
        await ctx.request.get(BASE + "/api/v1/me", {
            headers: { Authorization: "Bearer " + token },
        })
    ).json();
    const user = me?.data?.user ?? null;

    await ctx.addInitScript(
        ([t, u]) => {
            localStorage.clear();
            localStorage.setItem("token", t);
            localStorage.setItem("user", u);
        },
        [token, JSON.stringify(user)],
    );

    return { ctx, page, token, user };
}

test.describe("Витрина курсов", () => {
    test("запись открывает материал, отписка закрывает", async ({
        browser,
    }) => {
        const errors = [];
        const admin = await session(browser, ADMIN);
        const headers = { Authorization: "Bearer " + admin.token };
        admin.page.on("pageerror", (e) =>
            errors.push(e.message.split("\n")[0]),
        );

        // Идентификаторы нужны в finally, даже если создание упало на
        // полпути: фикстуры заводятся ДО try, и падение на expect() раньше
        // оставляло группу и пользователя в боевой базе.
        let groupId = null;
        let userId = null;

        try {
            const groupRes = await admin.page.request.post(
                BASE + "/api/groups",
                {
                    headers,
                    data: { groupname: `E2E Каталог ${unique()}` },
                },
            );
            expect(groupRes.status()).toBe(201);
            groupId = (await groupRes.json()).data.id;

            const fio = `E2Е Каталог ${unique()}`;
            const reg = await admin.page.request.post(BASE + "/api/register", {
                headers,
                data: {
                    fio,
                    password: PASSWORD,
                    password_confirmation: PASSWORD,
                    group_id: groupId,
                },
            });
            expect(reg.status()).toBe(201);
            userId = (await reg.json()).data.user?.id;

            const trainee = await session(browser, { fio, password: PASSWORD });
            trainee.page.on("pageerror", (e) =>
                errors.push(e.message.split("\n")[0]),
            );

            await trainee.page.goto(BASE + "/#/catalog", {
                waitUntil: "domcontentloaded",
            });
            await trainee.page.waitForTimeout(2200);

            await expect(trainee.page.locator("h1").first()).toContainText(
                "Каталог курсов",
            );

            const cards = trainee.page.locator(
                '[data-test="catalog-card"], [data-test="catalog-card-enrolled"]',
            );
            const total = await cards.count();
            expect(total, "витрина показывает курсы").toBeGreaterThan(0);

            // До записи материала нет.
            expect(
                await trainee.page
                    .locator('[data-test="catalog-open"]')
                    .count(),
                "незаписанному не показываем ссылку на материал",
            ).toBe(0);

            const enrollButton = trainee.page
                .locator('[data-test="catalog-enroll"]')
                .first();
            const card = enrollButton.locator("xpath=ancestor::article");
            const title = (
                await card.locator(".u-card__title").innerText()
            ).trim();

            // Материал этого курса закрыт.
            const catalogBefore = (
                await (
                    await trainee.ctx.request.get(BASE + "/api/catalog", {
                        headers: { Authorization: "Bearer " + trainee.token },
                    })
                ).json()
            ).data.items;
            const target = catalogBefore.find((c) => c.title.trim() === title);
            expect(target, "курс найден в витрине").toBeTruthy();

            const closed = await trainee.ctx.request.get(
                BASE + `/api/course/${target.id}`,
                {
                    headers: { Authorization: "Bearer " + trainee.token },
                },
            );
            expect(closed.status(), "до записи материал закрыт").toBe(403);

            // Записываемся.
            await enrollButton.click();
            await trainee.page.waitForTimeout(1600);

            await expect(
                trainee.page
                    .locator(".u-badge", { hasText: "Вы записаны" })
                    .first(),
                "появилась отметка о записи",
            ).toBeVisible();

            const opened = await trainee.ctx.request.get(
                BASE + `/api/course/${target.id}`,
                {
                    headers: { Authorization: "Bearer " + trainee.token },
                },
            );
            expect(opened.status(), "после записи материал открыт").toBe(200);

            expect(
                await trainee.page
                    .locator('[data-test="catalog-open"]')
                    .count(),
                "ссылка на материал появилась",
            ).toBeGreaterThan(0);

            // Поиск сужает витрину.
            await trainee.page
                .locator('[data-test="catalog-search"] input')
                .fill(title.slice(0, 8));
            await trainee.page.waitForTimeout(1500);
            const filtered = await trainee.page
                .locator(
                    '[data-test="catalog-card"], [data-test="catalog-card-enrolled"]',
                )
                .count();
            expect(filtered, "поиск сузил выдачу").toBeLessThanOrEqual(total);

            // Отписываемся.
            await trainee.page
                .locator('[data-test="catalog-search"] input')
                .fill("");
            await trainee.page.waitForTimeout(1400);
            await trainee.page
                .locator('[data-test="catalog-leave"]')
                .first()
                .click();
            await trainee.page.waitForTimeout(1600);

            const closedAgain = await trainee.ctx.request.get(
                BASE + `/api/course/${target.id}`,
                {
                    headers: { Authorization: "Bearer " + trainee.token },
                },
            );
            expect(
                closedAgain.status(),
                "после отписки материал снова закрыт",
            ).toBe(403);

            await trainee.ctx.close();
            expect(errors, "ошибок JS быть не должно").toEqual([]);
        } finally {
            if (userId)
                await admin.page.request.delete(`${BASE}/api/user/${userId}`, {
                    headers,
                });
            if (groupId)
                await admin.page.request.delete(
                    `${BASE}/api/groups/${groupId}`,
                    { headers },
                );
            await admin.ctx.close();
        }
    });
});
