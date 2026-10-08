import { reactive, readonly } from "vue";

/**
 * Глобальный стек уведомлений.
 *
 * Зачем отдельный сервис, если уже есть AppToast:
 *
 *  1. AppToast рассчитан на ОДНО сообщение: один экран — один
 *     v-model, одно alert/alertType/snackbarText. Если событий два
 *     подряд, второе перекрывало первое, а текст первого терялся.
 *     Здесь сообщения живут в очереди и показываются все.
 *  2. Состояние не нужно дублировать в каждом компоненте: раньше
 *     alert/alertType/snackbarText объявлялись в data примерно
 *     двадцати страниц, и одинаковый код копился вместе с ошибками:
 *     в одной тосте забывали role для скринридера, в другой — кнопку
 *     закрытия.
 *  3. Отмена действия («Удалено. Отменить») невозможна, когда тост
 *     живёт в компоненте и умирает вместе с ним: нужно общее место,
 *     где действие переживает само сообщение.
 *
 * Состояние — на уровне модуля, а не в provide(): так сервис
 * работает и из composable, и из обычных Options API компонентов, где
 * composable вызывается один раз на модуль, а не на компонент.
 *
 * Таймеры намеренно не хранятся в состоянии: они относятся к
 * показа, а не к данным, и не должны попадать в reactive-объект
 * (иначе отладочные инструменты показывают мусорные значения).
 */

const items = reactive([]);

let nextId = 1;

/** Таймеры скрытия по id: нужны, чтобы отменить опоздавший таймер. */
const timers = new Map();

/** Допустимые типы сообщений. */
const TYPES = ["success", "error", "warning", "info"];

/** Значения по умолчанию для каждого типа. */
const DEFAULTS = {
  success: { color: "success", icon: "mdi-check-circle-outline", timeout: 4000 },
  error: { color: "error", icon: "mdi-alert-circle-outline", timeout: 8000 },
  warning: { color: "warning", icon: "mdi-alert-outline", timeout: 6000 },
  info: { color: "primary", icon: "mdi-information-outline", timeout: 5000 },
};

/**
 * Ошибки живут дольше остальных: их успевают заметить и прочитать.
 * Число в сообщении обрезается — экранная ошибка сервера может быть
 * многострочной, и такой тост занимает пол-экрана.
 */
function normalizeText(text) {
  const value = typeof text === "string" ? text.trim() : "";

  if (value.length > 300) {
    return value.slice(0, 297) + "…";
  }

  return value;
}

function push(type, text, options = {}) {
  const config = DEFAULTS[type] || DEFAULTS.info;
  const id = nextId++;

  const item = reactive({
    id,
    type,
    text: normalizeText(text),
    color: options.color || config.color,
    icon: options.icon || config.icon,
    timeout: options.timeout ?? config.timeout,
    // assertive только на ошибке: скринридер прерывает текущую речь.
    live: type === "error" ? "assertive" : "polite",
    action: options.action
      ? {
          label: options.action.label,
          handler: options.action.handler,
        }
      : null,
  });

  items.push(item);

  /*
   * Больше пяти сообщений на экране — это уже не уведомления, а шум:
   * при потоке ошибок (например, пять неудачных запросов подряд) они
   * перекрывают интерфейс. Старое просто убирается.
   */
  while (items.length > 5) {
    dismiss(items[0].id);
  }

  // timeout: 0 или false — сообщение держится до ручного закрытия.
  if (item.timeout > 0) {
    timers.set(
      id,
      setTimeout(() => dismiss(id), item.timeout),
    );
  }

  return id;
}

function dismiss(id) {
  const timer = timers.get(id);

  if (timer) {
    clearTimeout(timer);
    timers.delete(id);
  }

  const index = items.findIndex((item) => item.id === id);

  if (index !== -1) {
    items.splice(index, 1);
  }
}

/**
 * Действие внутри тоста — это «отмена» удаления и подобное.
 *
 * Выполняется и сразу закрывает сообщение: оставить на экране тост с
 * уже выполненным действием незачем.
 */
function runAction(item) {
  try {
    item.action?.handler?.();
  } finally {
    dismiss(item.id);
  }
}

/** Очистить всё — например, при выходе из приложения. */
function clear() {
  timers.forEach((timer) => clearTimeout(timer));
  timers.clear();
  items.splice(0, items.length);
}

const toast = {
  /**
   * Показать сообщение по ИМЕНИ типа: toast.byType(type, text).
   *
   * Нужен там, где тип приходит параметром из данных (страница хранит
   * его в своём состоянии). Раньше каждая такая страница писала у себя
   * одинаковую проверку вида `const fn = toast[type]; (typeof fn ===
   * 'function' ? fn : toast.info)(text)`, и в одной из страниц такая
   * проверка дала ошибку времени выполнения «Cannot access ... before
   * initialization». Логика выбора типа — одна, в сервисе.
   *
   * Неизвестный тип показывается как info: молча пропавший результат
   * действия хуже информационного сообщения.
   */
  byType(type, text, options) {
    return push(TYPES.includes(type) ? type : "info", text, options);
  },

  success: (text, options) => push("success", text, options),
  error: (text, options) => push("error", text, options),
  warning: (text, options) => push("warning", text, options),
  info: (text, options) => push("info", text, options),

  dismiss,
  runAction,
  clear,

  /** Текст без сообщения — молчаливый сбой ничего не сообщает. */
  fromError(err, fallback = "") {
    return (
      err?.response?.data?.error ||
      err?.response?.data?.message ||
      err?.message ||
      fallback
    );
  },
};

/**
 * Точка доступа для компонентов.
 *
 * Options API: `import { toast } from "composables/useToast"`.
 * Composition API: `const { success } = useToast()`.
 */
export function useToast() {
  return { items: readonly(items), toast };
}

export { items as toastItems, dismiss, runAction, clear };

/*
 * Именованный экспорт нужен рядом с default: страницы в Options API
 * импортируют `{ toast }`, а не дефолтный модуль, — иначе пришлось бы
 * писать `import toast from` в каждом файле.
 */
export { toast };

export default toast;