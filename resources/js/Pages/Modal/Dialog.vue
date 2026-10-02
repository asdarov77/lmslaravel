<template>
  <v-dialog
    :model-value="dialog"
    max-width="500"
    @update:model-value="$emit('update:dialog', $event)"
  >
    <v-card>
      <v-card-title class="dialog__title">{{ title }}</v-card-title>
      <v-card-text>{{ text }}</v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="cancel">{{ $t("common.cancel") }}</v-btn>
        <v-btn color="error" variant="flat" @click="agree">{{ confirmLabel }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
/**
 * Диалог подтверждения.
 *
 * Что было не так:
 *  1. props: { text: Text, title: Text } — `Text` не существует,
 *     Vue ругался «Invalid prop type» и подставлял тип по умолчанию.
 *  2. data() возвращал `dialog`, а `dialog` уже был объявлен prop —
 *     конфликт имён, ошибка Vue при создании компонента.
 *  3. `:model-value="dialog"` без обработчика: значение нельзя было
 *     закрыть через v-model.
 *  4. Классы Vuetify 2: `grey lighten-2`, `text-h5`,
 *     `red darken-1 text`, `green darken-1 text` — в Vuetify 3
 *     не работают, поэтому кнопки теряли цвет и выглядели
 *     как обычные серые ссылки.
 *  5. Подписи кнопок были зашиты по-русски.
 *
 * Актуальные страницы используют components/ui/ConfirmDialog.vue;
 * этот файл оставлен как совместимый базовый вариант.
 */
export default {
  name: "Dialog",

  props: {
    dialog: { type: Boolean, default: false },
    text: { type: String, default: "" },
    title: { type: String, default: "" },
    confirmLabel: { type: String, default: "" },
  },

  emits: ["update:dialog", "dialogClose", "confirm"],

  computed: {
    resolvedConfirmLabel() {
      return this.confirmLabel || this.$t("common.delete");
    },
  },

  methods: {
    cancel() {
      this.$emit("update:dialog", false);
      this.$emit("dialogClose");
    },

    agree() {
      this.$emit("update:dialog", false);
      this.$emit("dialogClose");
      this.$emit("confirm");
    },
  },
};
</script>

<style scoped>
.dialog__title {
  font-size: 1.125rem;
  font-weight: 600;
}
</style>
