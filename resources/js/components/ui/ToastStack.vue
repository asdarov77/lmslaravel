<template>
  <!--
    Стек уведомлений приложения.

    Раньше на каждой странице был свой v-snackbar с одним сообщением, и
    два события подряд перекрывали друг друга: второе показывалось,
    первое исчезало без объяснения. Здесь сообщения накапливаются и
    показываются все, а порядок сохраняется — снизу вверх, чтобы
    последнее событие было ближе к пользователю.

    Список — единая область для озвучивания скринридером
    (role=status), но тексты внутри различаются assertive/polite:
    assertive на ошибке прерывает текущую речь, polite ставит сообщение
    в очередь. Смешивать оба на одном контейнере нельзя.
  -->
  <div
    class="toast-stack"
    :class="{ 'toast-stack--interactive': items.length > 0 }"
    role="status"
    aria-live="polite"
  >
    <TransitionGroup name="toast">
      <div
        v-for="item in items"
        :key="item.id"
        class="toast-stack__item"
        :data-test="'toast-' + item.type"
      >
        <v-icon :icon="item.icon" size="20" aria-hidden="true"></v-icon>

        <span class="toast-stack__text">{{ item.text }}</span>

        <!--
          Кнопка действия — «Отменить» после удаления и подобное.
          Она появляется только когда действие передано, поэтому пустого
          серого прямоугольника в тосте нет.
        -->
        <v-btn
          v-if="item.action"
          variant="text"
          size="small"
          class="toast-stack__action"
          @click="runAction(item)"
        >
          {{ item.action.label }}
        </v-btn>

        <v-btn
          icon="mdi-close"
          variant="text"
          size="x-small"
          :aria-label="$t('common.close')"
          @click="dismiss(item.id)"
        ></v-btn>

        <!--
          Текст ошибки объявляется assertive: он прерыет озвучивание.
          Отдельный узел нужен, потому что aria-live нельзя менять у
          одного и того же контейнера на ходу — часть скринридеров
          применяет значение только в момент появления узла.
        -->
        <span class="u-sr-only" :aria-live="item.live">{{ item.text }}</span>
      </div>
    </TransitionGroup>
  </div>
</template>

<script>
import { toastItems, dismiss, runAction } from "../../composables/useToast";

export default {
  name: "ToastStack",

  data: () => ({
    // Ссылка на реактивный массив сервиса, а не его копия: копия
    // перестала бы обновляться после первого показа.
    items: toastItems,
  }),

  methods: {
    dismiss,
    runAction,
  },
};
</script>

<style scoped>
/*
 * Позиция и поведение — из дизайн-системы, а не из компонента: иначе
 * тосты будут выглядеть по-разному на разных сборках Vuetify.
 */
.toast-stack {
  position: fixed;
  right: var(--sp-4);
  bottom: var(--sp-4);
  z-index: 3000;
  display: flex;
  flex-direction: column-reverse;
  gap: var(--sp-2);
  width: min(420px, calc(100vw - 2 * var(--sp-4)));
  pointer-events: none;
}

.toast-stack__item {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-3);
  border-radius: var(--radius-md);
  background: rgb(var(--v-theme-surface));
  color: rgb(var(--v-theme-on-surface));
  border: 1px solid rgb(var(--v-theme-on-surface) / 0.12);
  box-shadow: var(--shadow-2);
  /* Контейнер не перехватывает клики, а сам тост — перехватывает. */
  pointer-events: auto;
}

.toast-stack__text {
  flex: 1 1 auto;
  min-width: 0;
  font-size: 0.9rem;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.toast-stack__action {
  flex: 0 0 auto;
}

.toast-stack--interactive {
  /* Пустой стек не должен ловить события мыши у элементов под ним. */
  pointer-events: none;
}

/* Появление и уход: без них тосты «прыгают» при отладке. */
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>