<template>
  <v-menu v-if="loggedIn" location="bottom end" offset="8">
    <template #activator="{ props }">
      <!--
        Активатор — кнопка, а не абзац с вложенной кнопкой: вложенность
        ломала фокус и давала клик по имени, который ничего не делал.
        Аватар с фото или инициалами: раньше был жёстко зашитый адрес
        avataaars.io, одинаковый для всех пользователей и зависящий от
        внешнего сервиса.
      -->
      <v-btn v-bind="props" variant="text" class="u-account__btn" data-test="account-open">
        <v-avatar size="32" color="primary" class="mr-2">
          <v-img v-if="avatar" :src="avatar" :alt="fio" />
          <span v-else class="text-caption font-weight-bold">{{ initials }}</span>
        </v-avatar>

        <span class="d-none d-sm-inline">{{ fio }}</span>

        <v-icon end>mdi-chevron-down</v-icon>
      </v-btn>
    </template>

    <v-card width="300" class="u-account">
      <!-- Шапка: кто пользователь. Без неё меню не отвечало на вопрос
           «это моё меню или чужое». -->
      <v-card-item class="u-account__header">
        <template #prepend>
          <v-avatar size="44" color="primary">
            <v-img v-if="avatar" :src="avatar" :alt="fio" />
            <span v-else class="text-subtitle-2 font-weight-bold">{{ initials }}</span>
          </v-avatar>
        </template>

        <v-card-title class="text-body-1 font-weight-medium">{{ fio }}</v-card-title>
        <v-card-subtitle class="text-caption">
          {{ roleLabel }}
        </v-card-subtitle>
      </v-card-item>

      <v-divider />

      <v-list density="compact" nav>
        <v-list-item
          v-for="item in items"
          :key="item.key"
          :to="item.link"
          :prepend-icon="item.icon"
          :title="item.title"
          data-test="account-item"
        />

        <v-divider class="my-1" />

        <!--
          Выход только здесь. В сайдбаре он был отдельной закреплённой
          кнопкой, и «Выход» оказывался в двух местах интерфейса.
        -->
        <v-list-item
          prepend-icon="mdi-logout"
          :title="$t('app.menu.logout')"
          class="text-error"
          data-test="account-logout"
          @click="logout"
        />
      </v-list>
    </v-card>
  </v-menu>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { accountItems } from '../../navigation'
import { routePermissionIndex, requiredPermissions } from '../../utils/routePermissions'

/**
 * Меню профиля.
 *
 * Было пять пунктов с одной иконкой, все — на /my, без локализации и без
 * «шапки» пользователя. Теперь это то, чем и должно быть меню человека:
 * кто вошёл, куда он может пойти по своей роли, и выход.
 *
 * Пункты берутся из того же конфига навигации, что и сайдбар, и так же
 * фильтруются по правам из маршрутов: пункт, ведущий в 403, хуже, чем
 * его отсутствие.
 */
export default {
  data: () => ({
    requiredBy: routePermissionIndex(),
  }),

  computed: {
    ...mapState('Auth', ['user', 'accessToken']),
    ...mapGetters('Auth', ['can']),

    loggedIn() {
      return Boolean(this.accessToken && this.user)
    },

    fio() {
      return this.user?.fio || this.$t('account.anon')
    },

    roleLabel() {
      const role = this.user?.role

      if (!role) {
        return this.$t('account.noRole')
      }

      // Роль приходит из БД по-русски; ключ есть только для известных.
      const key = `account.roles.${role}`

      return this.$te(key) ? this.$t(key) : role
    },

    avatar() {
      const url = this.user?.avatar

      // Внешние сервисы аватаров больше не используются: картинка из
      // чужого домена — это и лишний запрос, и утечка реферера.
      return typeof url === 'string' && url.startsWith('http') ? url : null
    },

    initials() {
      const parts = String(this.fio).trim().split(/\s+/).slice(0, 2)

      return parts.map((p) => p.charAt(0).toUpperCase()).join('') || '?'
    },

    items() {
      return accountItems
        .filter((item) => {
          const required = requiredPermissions(this.requiredBy, item.link)

          return !required.length || this.can(...required)
        })
        .map((item) => ({ ...item, title: this.$t(item.titleKey) }))
    },
  },

  methods: {
    logout() {
      this.$store.dispatch('Auth/logout')
      this.$router.push({ name: 'login' })
    },
  },
}
</script>

<style scoped>
.u-account__btn {
  text-transform: none;
}

.u-account__header {
  padding-top: 12px;
}
</style>