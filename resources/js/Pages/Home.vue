<template>
  <component :is="viewComponent"></component>
</template>

<script>
import UserPage from "./User/UserPage.vue";
import MyAccount from "./MyAccount.vue";
import { mapState } from "vuex";

const roleComponentMapping = {
  'Обучаемый': UserPage,
  'Администратор': MyAccount,
  'Инструктор': MyAccount
}

export default {
    computed: {
      // Защита от undefined: если пользователь ещё не загружен, используем пустой объект
      ...mapState("Auth", { authUser: (state) => state.user || {} }),
      viewComponent() {
        const role = this.authUser?.role;
        return roleComponentMapping[role] || null;
      },
    },
}
</script>

