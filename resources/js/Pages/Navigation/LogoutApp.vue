<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
  <v-list-item
    prepend-icon="mdi-location-exit"
    :title="t('app.menu.logout')"
    v-on:click="logout"
    class="logout-btn"
  ></v-list-item>
</template>

<script>

export default {
  components: {},
  data() {
    return {};
  },
  methods: {
    /**
     * Выход на страницу авторизации — тем же порядком, что и в меню
     * профиля: сначала гасится сессия, затем меняется маршрут.
     * Обратный порядок оставлял приложение в промежуточном состоянии,
     * где пользователь уже на /login, но ещё считается авторизованным.
     */
    async logout() {
      try {
        await this.$store.dispatch('Auth/logout')
        await this.$store.dispatch('Ui/logout')
      } catch {
        // Отказ разлогина не должен вылезать в обработчик клика:
        // локальная сессия всё равно закрывается, маршрут меняется ниже.
      } finally {
        await this.$router.push({ name: 'login' }).catch(() => {})
      }
    },
  },
};
</script>
<!-- <style>
.logout-btn {
  background-color: white;
  height: 80px;
}
</style> -->
