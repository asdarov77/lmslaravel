# LMS UI Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the whole LMS interface (~40 routes) to one visual language per `docs/design-redesign-plan.md` and close what the October 2026 audit left open: quality pass (a11y, i18n, states, transitions, e2e). No new UI libraries; backend-dependent items are documented, not built.

**Architecture:** Single source of truth `resources/css/tokens.css`; Vuetify 3 theme reads the same tokens; all pages on `PageHeader + u-card + DataTable/FormCard`; complex forms via `useForm` + `FormCard`/`FormField` + dirty-guard; animation ≤ 250 ms with `prefers-reduced-motion` gate.

**Tech Stack:** Laravel 11, Vue 3, Vuetify 3, Vite, Vitest (719 tests), Playwright, CSS custom properties.

**Spec:** `docs/superpowers/specs/2026-10-16-lms-ui-redesign.md` (argues from `docs/design-redesign-plan.md` and `docs/ui-design.md`).

## Global Constraints

- Colors/radii/shadows/durations only from `resources/css/tokens.css` — no hex, no `elevation-12`, no inline `style="width:` in `resources/js/Pages/**` (enforced by `tools/lint-design-pillars.sh`).
- Every screen: loading (skeleton), empty, error (retry), success (`docs/ui-design.md`).
- Complex forms: schema → `FormCard`/`FormField` → `useForm` validation → dirty-guard → auto-draft; no manual `fieldErrors`.
- All user strings via `$t()` in `ru.json/en.json` (verified by `scripts/i18n_check.py`).
- Motion: `--dur-fast/base/slow`, ease-out; `prefers-reduced-motion` disables motion.
- Icon set: MDI only.

## Review Focus

1. Hex color or inline width in a migrated page → `lint-design-pillars.sh` fails.
2. Complex form submits invalid data or loses draft on navigate → `useForm`/dirty-guard tests fail.
3. Motion plays under `prefers-reduced-motion` → matchMedia gate fails.
4. Table/page missing skeleton/empty/error state → missing-state audit per route fails.
5. Hardcoded Russian string in component → `i18n_check.py` fails.

## File Structure

- Modify: `resources/css/tokens.css` — only if spec's palette changes (not planned; frozen).
- Modify: `resources/js/App.vue` — page transitions wiring (if not already).
- Modify: `resources/js/Pages/AuthPage.vue` — i18n labels, states.
- Modify: `resources/js/Pages/Dashboard/TraineeDashboard.vue`, `ManagerDashboard.vue`, `RoleDashboard.vue` — states + backend-blocked blocks documented.
- Modify: `resources/js/Pages/Course/CourseWizard.vue` — i18n, states; schedule/group-assignment fields NOT added (backend-blocked).
- Modify: `resources/js/components/ui/DataTable.vue` — empty/skeleton/error coverage verified.
- Create: `tests/E2E/ui-redesign-smoke.spec.js` — login → course → exam → result.
- Modify: `resources/js/locales/ru.json`, `en.json` — extracted strings.
- Modify: `docs/superpowers/specs/2026-10-16-lms-ui-redesign.md` — record decisions + backend-blocked list (Task 1).

## Tasks

### Task 1: Аудит текущего состояния vs дорожная карта + зафиксировать решения

**Files:**
- Modify: `docs/superpowers/specs/2026-10-16-lms-ui-redesign.md` (decisions table + backend-blocked list).

**Interfaces:**
- Consumes: `docs/design-redesign-plan.md` (audit results), current repo state.
- Produces: updated spec with decisions (email/табельный вход, веб-only, акцент `#0d9488`, `useForm`, приоритет Фазы 4 — отчёты) and honest backend-blocked list.

- [ ] **Step 1: Run design-pillars lint**

Run: `bash tools/lint-design-pillars.sh`
Expected: exit 0, no new violations in `Pages/**`; LEGACY_FILES unchanged.

- [ ] **Step 2: Run build and unit tests**

Run: `npm run build` and `npm test`
Expected: build passes (116 `.vue`), 719 tests across 60 files pass.

- [ ] **Step 3: Run i18n check**

Run: `python scripts/i18n_check.py`
Expected: no unresolved hardcoded keys reported.

- [ ] **Step 4: Record decisions and backend-blocked list in spec**

Edit `docs/superpowers/specs/2026-10-16-lms-ui-redesign.md`: fill decisions table and «Что требует бэкенда» section verbatim.

- [ ] **Step 5: Commit**

```bash
git add docs/superpowers/specs/2026-10-16-lms-ui-redesign.md
git commit -m "docs(spec): record UI redesign decisions and remaining scope"
```

### Task 2: A11y проход ключевых страниц

**Files:**
- Modify: `resources/js/Pages/AuthPage.vue`
- Modify: `resources/js/Pages/Dashboard/TraineeDashboard.vue`
- Modify: `resources/js/Pages/Dashboard/ManagerDashboard.vue`
- Modify: `resources/js/Pages/Dashboard/RoleDashboard.vue`
- Modify: `resources/js/Pages/Course/CourseWizard.vue`
- Modify: `resources/js/components/ui/ToastStack.vue` (aria-live on toasts/errors)
- Modify: `resources/js/components/ui/DataTable.vue` (keyboard navigation, focus-visible ring)

**Interfaces:**
- Consumes: existing component props/events (no signature changes).
- Produces: contrast AA on new palettes, `focus-visible` 2px accent ring, labels on all fields, `aria-live` on toasts/form errors, keyboard navigation of tables and course tree.

- [ ] **Step 1: Write a11y checks per page**

Contrast: text ≥ 4.5:1 on `--c-bg`/`--c-surface`; focus-visible ring present on every interactive; every input has visible label (`$t()`); toasts/errors use `aria-live="polite"`; tables + course tree navigable by Tab/arrows.

- [ ] **Step 2: Implement fixes in listed files**

One change per file matching the interfaces above; no new CSS values outside `tokens.css`.

- [ ] **Step 3: Verify with unit + lint**

Run: `npm test` and `bash tools/lint-design-pillars.sh`
Expected: PASS; lint exit 0.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/AuthPage.vue resources/js/Pages/Dashboard/ resources/js/Pages/Course/CourseWizard.vue resources/js/components/ui/ToastStack.vue resources/js/components/ui/DataTable.vue
git commit -m "feat(ui): a11y pass on key pages"
```

### Task 3: i18n — вынести хардкод-строки в словари

**Files:**
- Modify: `resources/js/locales/ru.json`
- Modify: `resources/js/locales/en.json`
- Modify: `resources/js/Pages/AuthPage.vue` (and any other `Pages/**` / `components/**` with hardcoded Russian labels found by grep)

**Interfaces:**
- Consumes: existing `$t()` keys; new keys added with Russian + English values.
- Produces: zero hardcoded user-visible Russian strings in `Pages/**` and `components/**` (except dictionary files).

- [ ] **Step 1: Find hardcoded strings**

Run: `grep -rnE "[А-Яа-я]" resources/js/Pages resources/js/components --include="*.vue" --include="*.js" | grep -v '$t(' | grep -v locales`
Expected: list of candidate strings (excluding legitimate dictionary content).

- [ ] **Step 2: Extract into ru.json/en.json**

Add keys for each candidate; replace hardcoded text with `$t('key')`.

- [ ] **Step 3: Verify**

Run: `python scripts/i18n_check.py`
Expected: clean; `npm run build` passes.

- [ ] **Step 4: Commit**

```bash
git add resources/js/locales/ru.json resources/js/locales/en.json resources/js/Pages/
git commit -m "feat(i18n): extract hardcoded strings to dictionaries"
```

### Task 4: Состояния всех списков (скелетон / пусто / ошибка / success)

**Files:**
- Modify: `resources/js/components/ui/DataTable.vue` (verify all four states)
- Modify: `resources/js/components/ui/EmptyState.vue` (verify reusable)
- Modify: `resources/js/components/ui/AppSkeleton.vue` (verify variants: table/cards/form/text)
- Modify: `resources/js/Pages/Catalog/CatalogPage.vue`, `resources/js/Pages/Assignment/List.vue`, `resources/js/Pages/User/UserList.vue` (apply states where missing)

**Interfaces:**
- Consumes: `meta.pagination` for server pagination; error object with message for retry state.
- Produces: skeleton while loading, «Нет … / Создать …» when empty, alert + «Повторить» on error, normal list on success; optimistic updates in tables per `docs/ui-design.md`.

- [ ] **Step 1: Inventory pages lacking states**

Inspect each table/page in `Pages/**`; list which lack skeleton/empty/error.

- [ ] **Step 2: Implement missing states**

Wrap list rendering: `v-skeleton-loader`/`AppSkeleton` during load, `EmptyState` with action button when empty, `v-alert` + retry button on error.

- [ ] **Step 3: Verify**

Run: `npm run build`, `npm test`, screenshot baseline comparison (`docs/screenshots-baseline`) if runnable.
Expected: build green, tests green, no regressed baseline screenshots.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ui/ resources/js/Pages/Catalog/CatalogPage.vue resources/js/Pages/Assignment/List.vue resources/js/Pages/User/UserList.vue
git commit -m "feat(ui): loading/empty/error states on all lists"
```

### Task 5: Page transitions на всех маршрутах

**Files:**
- Modify: `resources/js/App.vue` (wire `PageTransition.vue` into route views)
- Modify: `resources/js/components/ui/PageTransition.vue` (fade+slide 200 ms, `prefers-reduced-motion` off-switch)

**Interfaces:**
- Consumes: route change events; `prefers-reduced-motion` media query.
- Produces: fade+slide 200 ms transition on every route switch; zero motion when reduced motion preferred.

- [ ] **Step 1: Implement transition**

200 ms fade + slide, ease `--ease-out`, gated by `(prefers-reduced-motion: no-preference)`; instant swap otherwise.

- [ ] **Step 2: Wire to all routes**

Single transition wrapper around `<router-view>` in `App.vue`.

- [ ] **Step 3: Verify**

Run: `npm run build`, `npm test`; manual route switching in dev (`npm run dev`).
Expected: build green, tests green, transitions smooth, reduced-motion instant.

- [ ] **Step 4: Commit**

```bash
git add resources/js/App.vue resources/js/components/ui/PageTransition.vue
git commit -m "feat(ui): page transitions on all routes"
```

### Task 6: E2E smoke Playwright: вход → курс → экзамен → результат

**Files:**
- Create: `tests/E2E/ui-redesign-smoke.spec.js`

**Interfaces:**
- Consumes: running dev server (`VITE_APP_URL`); existing auth/course/exam flows.
- Produces: green smoke test covering the critical user path.

- [ ] **Step 1: Write smoke spec**

Login → open course → take exam → view result; assert each step reaches expected page/state.

- [ ] **Step 2: Run against dev server**

Run: `npm run build:e2e` (or serve) then `npx playwright test tests/E2E/ui-redesign-smoke.spec.js`
Expected: all assertions pass.

- [ ] **Step 3: Commit**

```bash
git add tests/E2E/ui-redesign-smoke.spec.js
git commit -m "test(e2e): smoke flow login → course → exam → result"
```

### Task 7: Финальная верификация и коммит плана

**Files:**
- No code changes (plan + spec already committed in Task 1; any spec corrections here).

**Interfaces:**
- Consumes: nothing.
- Produces: final green gate.

- [ ] **Step 1: Full gate**

Run: `bash tools/lint-design-pillars.sh`, `npm run build`, `npm test`, `python scripts/i18n_check.py`, `npx playwright test tests/E2E/ui-redesign-smoke.spec.js`
Expected: all green.

- [ ] **Step 2: Commit any stragglers**

```bash
git add -A
git commit -m "chore(ui): final redesign verification green"
```

## What is intentionally NOT in this plan (backend-blocked)

Per `docs/design-redesign-plan.md` §8, these require backend work first and are not built as UI:
- Course node reorder operation (drag-drop tree without save is an anti-pattern).
- WYSIWYG material editor + sanitization + `sandbox` on `srcdoc` iframe.
- Course fields `opens_at`/`closes_at` + group assignment in `courses`/`courseRules()`.
- Dashboard aggregates: «топ-курсы», «работы на проверку» in `GET /api/dashboard/summary`.
- Recommended courses for trainees (progress-based, not catalog-based).
