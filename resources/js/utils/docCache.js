/**
 * Клиентский LRU-кэш документов плеера (P0.3).
 *
 * Повторный клик по уже просмотренному модулю должен показать
 * srcdoc мгновенно, без цепочки getlink → signed-url → файл.
 * Ёмкость 15 документов: список листается последовательно,
 * глубже люди не ходят, а html материалы бывают тяжёлыми.
 *
 * Не реактивен намеренно: html в reactive-состоянии тормозил бы
 * отрисовку списка; кэш влияет только на скорость повторного клика.
 *
 * shortcut: stale-while-revalidate нет — закэшированный документ живёт
 * в пределах сессии страницы и не проверяет свежесть на сервере;
 * если материалы начнут обновляться под пользователями, добавить
 * фоновую дозагрузку после мгновенного показа.
 */
const DOC_CACHE_LIMIT = 15;

export function createDocCache() {
  const map = new Map();

  return {
    get(courseId, itemId) {
      const key = `${courseId}:${itemId}`;
      if (!map.has(key)) return null;
      // LRU: свежий доступ уходит в конец очереди.
      const value = map.get(key);
      map.delete(key);
      map.set(key, value);
      return value;
    },

    put(courseId, itemId, html) {
      if (!html) return;
      const key = `${courseId}:${itemId}`;
      map.delete(key);
      map.set(key, html);

      // Выбрасываем самые старые, пока не влезаем в лимит.
      while (map.size > DOC_CACHE_LIMIT) {
        const oldest = map.keys().next().value;
        map.delete(oldest);
      }
    },

    /** Точечный сброс: материал отредактирован методистом — старый doc вреден. */
    delete(courseId, itemId) {
      map.delete(`${courseId}:${itemId}`);
    },

    clear() {
      map.clear();
    },
  };
}
