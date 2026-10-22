<template>
  <nav class="u-nav" :aria-label="$t('app.menu.main')">
    <!--
      Пункты меню.

      Раньше секции сворачивались через v-list-group, а всё дерево меню
      было вынесено в отдельную компоненту RenderNavItem. Обе идеи были
      лишними и ломались:

      - у секций был свой флаг раскрытия в localStorage, поэтому меню
        «прыгало» между состояниями и само закрывалось;
      - scoped-стили родителя не применялись к элементам дочерней
        компоненты (её корень — v-if/v-else, а data-v не всегда попадал
        на v-list-item), из-за чего пункты выпадали из оформления —
        это и были артефакты.

      Теперь меню плоское по структуре (одна компонента, без вложенных
      рендереров) и ничего не сворачивается. Вложенность осталась только
      содержательная — подпункты показываются сразу, с отступом.
    -->
    <v-list density="compact" nav class="u-nav__list">
      <template v-for="section in visibleSections" :key="section.key">
        <div class="u-nav__section">{{ section.title }}</div>

        <template v-for="item in section.items" :key="item.key">
          <v-list-item
            :to="item.link"
            :prepend-icon="item.icon"
            :active="isActive(item)"
            class="u-nav__item"
          >
            <v-list-item-title class="u-nav__item-text" data-test="nav-item">{{ item.title }}</v-list-item-title>
          </v-list-item>

          <!-- Подпункты раскрыты всегда: скрывать их нечем — их мало. -->
          <template v-if="item.children && item.children.length">
            <v-list-item
              v-for="child in item.children"
              :key="child.key"
              :to="child.link"
              :prepend-icon="child.icon"
              :active="isActive(child)"
              class="u-nav__item u-nav__item--sub"
            >
              <v-list-item-title class="u-nav__item-text" data-test="nav-item-sub">{{ child.title }}</v-list-item-title>
            </v-list-item>
          </template>
        </template>
      </template>
    </v-list>

    <!-- Закреплённые курсы: видны только когда есть что показать. -->
    <div v-if="pinnedList.length" class="u-nav__pinned-shell">
      <div class="u-nav__pinned-head">
        <span class="u-nav__pinned-title">{{ $t('app.menu.pinned') }}</span>
        <button
          type="button"
          class="u-nav__pinned-clear"
          :title="$t('app.menu.unpinAll')"
          :aria-label="$t('app.menu.unpinAll')"
          @click="clearPinned"
        >
          <v-icon size="16" aria-hidden="true">mdi-close-all</v-icon>
        </button>
      </div>

      <div class="u-nav__pinned-list">
        <div
          v-for="course in pinnedList"
          :key="course.id"
          class="u-nav__pinned-item"
          data-test="pinned-course"
        >
          <router-link :to="course.link" class="u-nav__pinned-link">
            <v-icon icon="mdi-pin" size="16" aria-hidden="true" />
            <span class="u-nav__pinned-text">{{ course.title }}</span>
          </router-link>
          <button
            type="button"
            class="u-nav__unpin"
            :title="$t('app.menu.unpin')"
            :aria-label="$t('app.menu.unpin')"
            @click="unpin(course.id)"
          >
            <v-icon icon="mdi-close" size="14" aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>
  </nav>
</template>

<script>
import { mapGetters } from 'vuex'
import { navigationSections } from '../../navigation'
import { routePermissionIndex } from '../../utils/routePermissions'
import { visibleSections as buildVisibleSections } from '../../utils/navigationVisibility'
import { pinnedCourses } from '../../utils/pinnedCourses'

export default {
  name: 'LeftSideMenu',

  data: () => ({
    requiredBy: routePermissionIndex(),
    pinnedList: pinnedCourses.list(),
  }),

  computed: {
    ...mapGetters('Auth', ['can']),

    /**
     * Контекст видимости — тот же, что и у палитры команд: права берутся
     * из meta.permission маршрутов. Свой список прав в меню заведёл бы
     * третий источник правды, и пункт рано или поздно повёл бы в 403.
     */
    visibilityContext() {
      return {
        can: this.can,
        isManager: this.isManager,
        t: (key) => this.$t(key),
        index: this.requiredBy,
      }
    },

    isManager() {
      return this.can(
        'users.view', 'users.permissions', 'groups.view', 'groups.manage', 'courses.manage',
      )
    },

    visibleSections() {
      return buildVisibleSections(navigationSections, this.visibilityContext)
    },
  },

  mounted() {
    window.addEventListener('pinned-courses-changed', this.reloadPinned)
  },

  beforeUnmount() {
    window.removeEventListener('pinned-courses-changed', this.reloadPinned)
  },

  methods: {
    /**
     * Активен ли пункт.
     *
     * Совпадение по префиксу нужно для вложенных маршрутов (/reg внутри
     * /user, /course/:id/edit внутри /courses), а activeMatch — когда у
     * пункта свой префикс, не совпадающий с его адресом (/user/list
     * активен на всей ветке /user).
     */
    isActive(item) {
      const current = this.$route?.path || ''
      if (!current || !item.link) return false

      if (current === item.link || current.startsWith(item.link + '/')) return true

      return Boolean(item.activeMatch) && current.startsWith(item.activeMatch + '/')
    },

    unpin(id) {
      this.pinnedList = pinnedCourses.remove(id)
    },

    /**
     * Снять все закрепления — через remove() по каждому, потому что
     * метода clear() у pinnedCourses нет.
     */
    clearPinned() {
      let rest = this.pinnedList

      this.pinnedList.forEach(({ id }) => {
        rest = pinnedCourses.remove(id)
      })

      this.pinnedList = rest
      window.dispatchEvent(new CustomEvent('pinned-courses-changed'))
    },

    reloadPinned() {
      this.pinnedList = pinnedCourses.list()
    },
  },
}
</script>

<style scoped>
.u-nav {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--c-glass-bg);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border-right: 1px solid var(--c-glass-border);
}

.u-nav__list {
  flex: 1;
  overflow-y: auto;
  padding: var(--sp-2) var(--sp-2) var(--sp-4);
}

/* Заголовок секции. */
.u-nav__section {
  padding: var(--sp-3) var(--sp-3) var(--sp-1);
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--c-text-muted);
}

/* Пункт меню. */
.u-nav__item {
  min-height: 40px;
  border-radius: var(--radius-md);
  margin: 1px 0;
  color: var(--c-text-secondary);
  transition: background var(--dur-fast) var(--ease), color var(--dur-fast) var(--ease);
}

.u-nav__item:hover {
  background: var(--c-surface-2);
  color: var(--c-text);
}

.u-nav__item--active {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  box-shadow: var(--shadow-1);
}

.u-nav__item--sub {
  padding-inline-start: var(--sp-6);
  min-height: 36px;
}

.u-nav__item--sub.u-nav__item--active {
  color: var(--c-accent);
}

.u-nav__item-text {
  font-size: var(--fs-sm);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Закреплённые курсы. */
.u-nav__pinned-shell {
  border-top: 1px solid var(--c-glass-border);
  padding: var(--sp-2) var(--sp-2) var(--sp-3);
}

.u-nav__pinned-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
  padding: var(--sp-1) var(--sp-3) var(--sp-2);
}

.u-nav__pinned-title {
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: var(--c-text-muted);
}

.u-nav__pinned-clear,
.u-nav__unpin {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--c-text-muted);
  cursor: pointer;
  transition: color var(--dur-fast) var(--ease), background var(--dur-fast) var(--ease);
}

.u-nav__pinned-clear:hover,
.u-nav__pinned-remove:hover {
  color: var(--c-danger);
  background: var(--c-danger-soft);
}

.u-nav__pinned-item {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  border-radius: var(--radius-md);
  padding-inline-end: var(--sp-1);
}

.u-nav__pinned-item:hover {
  background: var(--c-surface-2);
}

.u-nav__pinned-link {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex: 1;
  min-width: 0;
  padding: var(--sp-2) var(--sp-3);
  color: var(--c-text-secondary);
  text-decoration: none;
}

.u-nav__pinned-text {
  font-size: var(--fs-sm);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
