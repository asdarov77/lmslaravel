import { onBeforeUnmount, onMounted, unref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

/**
 * useLeaveGuard — подтверждение ухода с несохранённой формы.
 *
 * Зачем: пользователь набирал длинную форму (курс, вопрос), случайно
 * кликал пункт меню и терял всё молча. Раньше защиты не было ни у
 * одной формы. Здесь два выхода, которые надо перекрыть:
 *   - переход внутри SPA → guard роутера;
 *   - закрытие вкладки/перезагрузка → beforeunload.
 *
 * `isDirty` — ref/getter/boolean. По умолчанию подтверждение идёт
 * через window.confirm; `confirm` можно переопределить (например,
 * завязать на собственный ConfirmDialog), если результат —
 * Promise<boolean> или boolean.
 *
 * Возвращает `{ bypassGuard }` — выставить перед программной
 * навигацией после успешного сохранения, чтобы не спрашивать лишний
 * раз.
 */
export function useLeaveGuard(
  isDirty,
  {
    message = 'На форме есть несохранённые изменения. Уйти и потерять их?',
    confirm = null,
    /** Отключить guard целиком (например, в режиме просмотра). */
    enabled = true,
  } = {}
) {
  const resolveDirty = typeof isDirty === 'function' ? isDirty : () => unref(isDirty)
  const isDirtyNow = () => (enabled === false ? false : Boolean(resolveDirty()))

  const ask =
    typeof confirm === 'function'
      ? confirm
      : (text) => Promise.resolve(typeof window !== 'undefined' ? window.confirm(text) : true)

  let bypass = false

  const bypassGuard = () => {
    bypass = true
  }

  const releaseGuard = () => {
    bypass = false
  }

  // Переход внутри SPA.
  try {
    onBeforeRouteLeave(async (to, from, next) => {
      if (bypass || !isDirtyNow()) {
        next()

        return
      }

      const ok = await ask(message)
      if (ok) {
        bypass = true
        next()
      } else {
        next(false)
      }
    })
  } catch (error) {
    // Компонент вне контекста роутера (тесты, изолированный рендер):
    // guard переходов недоступен, beforeunload всё равно работает.
  }

  // Закрытие/перезагрузка вкладки.
  const onBeforeUnload = (event) => {
    if (bypass || !isDirtyNow()) return undefined

    event.preventDefault()
    // Для старых браузеров (Safari < 15) returnValue обязателен.
    event.returnValue = message

    return message
  }

  onMounted(() => {
    if (typeof window === 'undefined') return
    window.addEventListener('beforeunload', onBeforeUnload)
  })

  onBeforeUnmount(() => {
    if (typeof window === 'undefined') return
    window.removeEventListener('beforeunload', onBeforeUnload)
  })

  return { bypassGuard, releaseGuard }
}

export default useLeaveGuard
