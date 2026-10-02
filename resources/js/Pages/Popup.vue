<template>
  <AppToast
    :model-value="alert"
    :text="snackbarText"
    :type="alertType"
    @update:model-value="onChange"
  />
</template>

<script>
import AppToast from "../components/ui/AppToast.vue";

/**
 * Адаптер над AppToast. Оставлен, потому что на него ссылается
 * 12 страниц: переводить их все разом — отдельная задача.
 *
 * Что исправлено:
 *  - был v-overlay: полноэкранная шторка перекрывала интерфейс
 *    на 3 секунды после каждого сохранения;
 *  - был <style scooped> — с опечаткой, поэтому правило
 *    `.v-overlay { top: 50% }` утекало на все оверлеи приложения,
 *    включая диалоги и выпадающие списки.
 */
export default {
  name: "popup",
  components: { AppToast },
  props: {
    snackbarText: { type: String, default: "" },
    alert: { type: Boolean, default: false },
    // Значения из старого API: "success"/"error".
    alertType: { type: String, default: "success" },
    overlay: { type: Boolean, default: false },
    // Раньше это был колбэк, переданный как prop; оставляем совместимость.
    alertFalse: { type: Function, default: null },
  },
  methods: {
    onChange(value) {
      if (!value && typeof this.alertFalse === "function") {
        this.alertFalse();
      }
      this.$emit("update:alert", value);
    },
  },
};
</script>
