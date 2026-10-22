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
          <!--
            Прогресс XP — необязательный элемент: если движок геймификации
            недоступен или права нет, меню продолжит работать без него.
            Один запрос, не блокирующий открытие меню.
          -->
          <template v-if="xp">
            &middot; {{ $t('gamification.xp') }} {{ xp }}
          </template>
        </v-card-subtitle>
      </v-card-item>

      <v-divider />

      <!--
        Строка переключателей, разбросанных ранее по шапке: тема,
        плотность, язык. Компактные сегменты, а не отдельные кнопки.
      -->
      <div class="u-account__toggles" role="group" :aria-label="$t('app.menu.main')">
        <ThemeToggle />
        <button
          type="button"
          class="u-account__seg"
          :aria-pressed="density !== 'compact'"
          :title="$t('app.density')"
          @click="toggleDensity"
        >
          <v-icon size="16" aria-hidden="true">mdi-view-compact</v-icon>
        </button>
        <div class="u-account__langs" role="group" :aria-label="$t('common.language')">
          <button
            type="button"
            class="u-account__seg"
            :class="{ 'u-account__seg--on': $i18n.locale === 'ru' }"
            :aria-pressed="$i18n.locale === 'ru'"
            aria-label="Русский"
            @click="setLocale('ru')"
          >RU</button>
          <button
            type="button"
            class="u-account__seg"
            :class="{ 'u-account__seg--on': $i18n.locale === 'en' }"
            :aria-pressed="$i18n.locale === 'en'"
            aria-label="English"
            @click="setLocale('en')"
          >EN</button>
        </div>
      </div>

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
import ThemeToggle from '../../components/ui/ThemeToggle.vue'
import density from '../../utils/density'
import $api from '../../api/httpClient'

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
  components: { ThemeToggle },

  data: () => ({
    requiredBy: routePermissionIndex(),
    xp: 0,
  }),

  computed: {
    ...mapState('Auth', ['user', 'accessToken']),
    ...mapGetters('Auth', ['can']),

    loggedIn() {
      return Boolean(this.accessToken && this.user)
    },

    density() {
      return density.read()
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

  mounted() {
    // XP приезжает отдельным запросом и подставляется, когда дойдёт.
    this.loadXp()
  },

  methods: {
    setLocale(value) {      this.$store.commit('Ui/SET_LANGUAGE', value)
      this.$i18n.locale = value
    },

    toggleDensity() {
      density.toggle()
      this.$store.commit('Ui/SET_DENSITY', density.read())
    },

    /**
     * XP-плашка. Запрос отдельный и необязательный: он не должен
     * блокировать открытие меню — при ошибке просто остаётся пустым.
     */
    async loadXp() {
      try {
        const response = await $api.get('/api/gamification/me')
        this.xp = Number(response?.data?.data?.xp) || 0
      } catch {
        this.xp = 0
      }
    },

    /**
     * Выход на страницу авторизации.
     *
     * Раньше переход выполнялся не дожидаясь разлогина: `dispatch` бросался
     * без await, и `push` уходил на /login в тот же тик, когда токен ещё
     * лежал в хранилище. Приложение успевало смонтировать форму
     * регистрации ещё «авторизованной» — она уходила в /api/groups и
     * оставляла тост «Список групп недоступен: войдите в систему»
     * сразу после выхода.
     *
     * Теперь сначала гасится сессия, и только потом меняется маршрут.
     */
    async logout() {
      try {
        await this.$store.dispatch('Auth/logout')
      } catch {
        // Отказ разлогина не должен вылезать в обработчик клика и
        // показываться пользователю: локальная сессия всё равно
        // закрывается в finally экшена, а маршрут меняется ниже.
      } finally {
        // Ошибка сети не должна оставить пользователя в приложении:
        // локальная сессия всё равно закрыта в finally экшена.
        await this.$router.push({ name: 'login' }).catch(() => {})
      }
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

/* Строка переключателей: тема, плотность, язык — компактные сегменты. */
.u-account__toggles {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-3);
}

.u-account__seg {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding: 0 var(--sp-2);
  border: 1px solid var(--c-border);
  border-radius: var(--radius-pill);
  background: transparent;
  color: var(--c-text-secondary);
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
  cursor: pointer;
  transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
}

.u-account__seg:hover {
  background: var(--c-surface-2);
}

.u-account__seg--on {
  background: var(--c-primary-soft);
  border-color: var(--c-primary);
  color: var(--c-primary);
}

.u-account__langs {
  display: inline-flex;
  gap: var(--sp-1);
  margin-left: auto;
}
</style>