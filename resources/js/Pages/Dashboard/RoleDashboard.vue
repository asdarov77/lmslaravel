<template>
  <ManagerDashboard v-if="isManager" />
  <TraineeDashboard v-else />
</template>

<script>
/**
 * Личный кабинет по роли.
 *
 * Раньше /dashboard отдавал дашборд обучаемого всем: администратор и
 * инструктор видели «Состояние вашего обучения на сегодня» и кнопку
 * «Открыть учебный план», которая им ничего не даёт.
 *
 * Что считается управляющим: право на людей, группы или курсы. Роль
 * здесь не проверяется — она могла быть не назначена, а право
 * выдано напрямую, и тогда кабинет должен соответствовать правам,
 * а не строке в базе. Сверка идёт через can(), тем же способом, что
 * и в меню.
 */
import { mapGetters } from 'vuex'
import TraineeDashboard from './TraineeDashboard.vue'
import ManagerDashboard from './ManagerDashboard.vue'

export default {
  name: 'RoleDashboard',

  components: { TraineeDashboard, ManagerDashboard },

  computed: {
    ...mapGetters('Auth', ['can']),

    isManager() {
      return this.can('users.view', 'users.permissions', 'groups.view', 'groups.manage', 'courses.manage')
    },
  },
}
</script>
