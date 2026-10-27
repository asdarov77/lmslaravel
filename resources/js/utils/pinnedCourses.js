/**
 * Закреплённые курсы.
 *
 * Зачем: у обучающего список курсов — это каталог из десятков позиций, и
 * свои три-четыре курса приходилось искать каждый раз. Закреплённый курс
 * доступен из бокового меню одной ссылкой.
 *
 * Где хранится: localStorage, а не бэкенд. Причина — закрепление это
 * предпочтение интерфейса конкретного пользователя на конкретном
 * устройстве: оно не влияет на данные курса, не требует прав и не должно
 * синхронизироваться между устройствами. Отдельной таблицы ради порядка
 * сортировки закладок не заводили.
 *
 * Формат записи: { id, title, link }. Ссылка хранится целиком, чтобы
 * закрепление не зависело от того, какая именно страница курса открыта
 * (содержание, описание или список уроков) — на неё и ведёт пункт меню.
 */

const STORAGE_KEY = 'pinned-courses';

const read = () => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];

    const parsed = JSON.parse(raw);

    if (!Array.isArray(parsed)) return [];

    // Отбрасываем мусор и старые записи: раньше ключом мог стать
    // id структуры курса, а не самого курса, и тогда пункт вёл в
    // несуществующий раздел. Такие записи удаляем, а не показываем
    // как «сломанную ссылку».
    return parsed.filter(
      (entry) => entry && Number.isInteger(entry.id) && typeof entry.title === 'string' && entry.title.length > 0,
    );
  } catch {
    return [];
  }
};

const write = (items) => {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Переполнение localStorage или приватный режим: закрепления
    // останутся в памяти до перезагрузки. Это не повод ронять страницу.
  }
};

export const pinnedCourses = {
  /** Список закреплённых курсов в порядке закрепления. */
  list() {
    return read();
  },

  isPinned(id) {
    return read().some((entry) => entry.id === id);
  },

  /**
   * Закрепить курс. Возвращает новое состояние, чтобы вызов не зависел
   * от мутации внутри.
   */
  add({ id, title, link }) {
    if (!Number.isInteger(id) || !title) return read();

    const items = read().filter((entry) => entry.id !== id);

    items.unshift({ id, title, link });

    // Раньше список рос без ограничения: после полугода работы в меню
    // оказывалось 40 закреплённых курсов и секция вытесняла основную
    // навигацию. Предел — 8, лишние закрепления отбрасываются (FIFO —
    // уходит самое старое).
    write(items.slice(0, MAX_PINNED));

    return read();
  },

  remove(id) {
    write(read().filter((entry) => entry.id !== id));

    return read();
  },

  toggle({ id, title, link }) {
    return this.isPinned(id) ? this.remove(id) : this.add({ id, title, link });
  },
};

const MAX_PINNED = 8;
