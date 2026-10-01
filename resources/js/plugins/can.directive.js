/**
 * Директива v-can — декларативная проверка прав в шаблонах (стиль Laravel Gate).
 *
 *   <button v-can="'courses.manage'">Изменить</button>
 *   <button v-can="['users.view', 'manage-users']">Любой из</button>   // OR (по умолчанию)
 *   <div    v-can.all="['courses.view', 'categories.manage']">Оба</div> // AND
 *
 * Реализация:
 *  - элемент СКРЫВАЕТСЯ через display:none, а не удаляется из DOM.
 *    Удаление необратимо: при первой отрисовке права ещё не пришли
 *    (GET /api/v1/me выполняется асинхронно роутером), элемент исчезал
 *    навсегда и не возвращался после синхронизации. Так админ-панель
 *    выглядела «пустой» до перезагрузки.
 *  - пересчёт в hooks mounted/updated: права реактивны (Vuex-геттер),
 *    поэтому после смены прав видимость пересчитывается сама.
 *  - v-can верен только как UX-слой. Реальный запрет даёт бэкенд:
 *    middleware permission:* отвечает 403 независимо от DOM.
 */
import store from '../Store';

const HIDDEN_ATTR = 'data-v-can-hidden';

/** @param {unknown} value */
const normalize = (value) => {
  const list = (Array.isArray(value) ? value : [value])
    .filter((p) => p !== null && p !== undefined && p !== '')
    .map(String);
  return list;
};

/**
 * @param {Element} el
 * @param {import('vue').DirectiveBinding} binding
 */
const apply = (el, binding) => {
  const required = normalize(binding.value);

  // Без требований элемент доступен всем (как пункт меню без contentType).
  if (required.length === 0) {
    el.removeAttribute(HIDDEN_ATTR);
    el.style.removeProperty('display');
    return;
  }

  const requireAll = binding.modifiers.all === true;
  const allowed = requireAll
    ? required.every((p) => store.getters['Auth/can'](p))
    : required.some((p) => store.getters['Auth/can'](p));

  if (allowed) {
    el.removeAttribute(HIDDEN_ATTR);
    el.style.removeProperty('display');
  } else {
    el.setAttribute(HIDDEN_ATTR, 'true');
    el.style.setProperty('display', 'none', 'important');
  }
};

export const canDirective = {
  mounted: apply,
  updated: apply,
  // unbind сбрасывает стиль, чтобы не «протекать» на переиспользуемый DOM
  unmounted(el) {
    el.removeAttribute(HIDDEN_ATTR);
    el.style.removeProperty('display');
  },
};

export default canDirective;
