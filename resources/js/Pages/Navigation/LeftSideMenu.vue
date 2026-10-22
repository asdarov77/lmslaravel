<template>
  <nav class="u-nav" :class="{ 'u-nav--rail': rail }" :aria-label="$t('app.menu.main')">
    <button
      type="button"
      class="u-nav__rail-toggle"
      :title="rail ? $t('app.menu.expand') : $t('app.menu.collapse')"
      :aria-label="rail ? $t('app.menu.expand') : $t('app.menu.collapse')"
      @click="toggleRail"
    >
      <v-icon size="18" aria-hidden="true">{{ rail ? 'mdi-menu-open' : 'mdi-menu' }}</v-icon>
    </button>

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
    <!--
      «Продолжить обучение»: точка возврата из /api/my/dashboard
      (continue.course_id). Пункт динамический — outside статических
      секций navigation, появился как только обучаемый что-то открыл.
    -->
    <router-link
      v-if="resumeCourse"
      :to="{ name: 'courses.itemmani', query: { idEdit: resumeCourse.course_id } }"
      class="u-nav__resume"
      data-test="nav-resume"
    >
      <v-icon icon="mdi-play-circle" size="18" aria-hidden="true"></v-icon>
      <span class="u-nav__resume-text">{{ resumeCourse.title }}</span>
    </router-link>

    <v-list density="compact" nav class="u-nav__list">
      <template v-for="section in visibleSections" :key="section.key">
        <button
          type="button"
          class="u-nav__section u-nav__section--toggle"
          :aria-expanded="!isCollapsed(section.key) ? 'true' : 'false'"
          @click="toggleSection(section.key)"
        >
          {{ section.title }}
          <v-icon
            v-if="!rail"
            size="16"
            :class="{ 'u-nav__chevron--closed': isCollapsed(section.key) }"
            aria-hidden="true"
          >mdi-chevron-up</v-icon>
        </button>

        <template v-if="!isCollapsed(section.key) || rail">
          <template v-for="item in section.items" :key="item.key">
            <v-list-item
              :to="item.link"
              :prepend-icon="item.icon"
              :active="isActive(item)"
              class="u-nav__item"
              :title="rail ? item.title : undefined"
            >
              <v-list-item-title class="u-nav__item-text" data-test="nav-item">{{ item.title }}</v-list-item-title>
              <template #append>
                <v-badge
                  v-if="badges[item.badgeCounter]"
                  :content="badges[item.badgeCounter]"
                  inline
                  color="warning"
                ></v-badge>
              </template>
            </v-list-item>

            <!-- Подпункты раскрыты всегда: скрывать их нечем — их мало. -->
            <template v-if="!rail && item.children && item.children.length">
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
import $api from '../../api/httpClient'
import { unwrapResponse } from '../../api/envelope'
import { navigationSections } from '../../navigation'
import { routePermissionIndex } from '../../utils/routePermissions'
import { visibleSections as buildVisibleSections } from '../../utils/navigationVisibility'
import { pinnedCourses } from '../../utils/pinnedCourses'

/** Ключи localStorage для состояния меню. */
const NAV_COLLAPSED_KEY = 'ui-nav-collapsed'
const NAV_RAIL_KEY = 'ui-nav-rail'

function readCollapsed() {
  try {
    return JSON.parse(window.localStorage.getItem(NAV_COLLAPSED_KEY) || '{}') || {}
  } catch (e) {
    return {}
  }
}

export default {
  name: 'LeftSideMenu',

  data: () => ({
    requiredBy: routePermissionIndex(),
    pinnedList: pinnedCourses.list(),
    collapsed: readCollapsed(),
    rail: false,
    badges: {},
    resumeCourse: null,
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
    this.rail = window.localStorage.getItem(NAV_RAIL_KEY) === '1'

    // Бейджи: один запрос в фоне, тихий провал — меню работает и без них.
    $api.get('/api/menu-counters')
      .then((res) => {
        const data = unwrapResponse(res) || {}
        if (data.review_queue > 0) {
          this.badges = { review_queue: data.review_queue > 99 ? '99+' : data.review_queue }
        }
      })
      .catch(() => {})

    // «Продолжить»: только для обучаемого — у остальных длиннее обусловлено
    // своим дашбордом; провал тихий, меню и без этого пункта полноценно.
    $api.get('/api/my/dashboard')
      .then((res) => {
        const data = unwrapResponse(res) || {}
        if (data.continue && data.continue.course_id) {
          this.resumeCourse = data.continue
        }
      })
      .catch(() => {})
  },

  beforeUnmount() {
    window.removeEventListener('pinned-courses-changed', this.reloadPinned)
  },

  methods: {
    isCollapsed(key) {
      return Boolean(this.collapsed[key])
    },

    toggleSection(key) {
      if (this.rail) return

      this.collapsed = { ...this.collapsed, [key]: !this.collapsed[key] }
      try {
        window.localStorage.setItem(NAV_COLLAPSED_KEY, JSON.stringify(this.collapsed))
      } catch (e) {}
    },

    toggleRail() {
      this.rail = !this.rail
      try {
        window.localStorage.setItem(NAV_RAIL_KEY, this.rail ? '1' : '0')
      } catch (e) {}
      window.dispatchEvent(new CustomEvent('ui-nav-rail-changed'))
    },
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
.u-nav__section--toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: calc(100% - var(--sp-2));
  padding: var(--sp-3) var(--sp-3) var(--sp-1);
  margin: 0 var(--sp-1);
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  font-size: var(--fs-2xs);
  font-weight: var(--fw-semibold);
  letter-spacing: 0.05em;
  text-align: start;
  text-transform: uppercase;
  color: var(--c-text-muted);
  cursor: pointer;
}

.u-nav__section--toggle:hover {
  color: var(--c-text);
}

.u-nav__chevron--closed {
  transform: rotate(180deg);
  transition: transform var(--dur-fast) var(--ease);
}

.u-nav__rail-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  align-self: flex-end;
  width: 36px;
  height: 36px;
  margin: var(--sp-2) var(--sp-2) 0;
  border: none;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--c-text-muted);
  cursor: pointer;
}

.u-nav__rail-toggle:hover {
  background: var(--c-surface-2);
  color: var(--c-text);
}

/* Свернутый rail: только иконки, 56px. */
.u-nav--rail {
  align-items: stretch;
}

.u-nav--rail .u-nav__list {
  padding-inline: var(--sp-1);
}

.u-nav--rail .u-nav__section--toggle,
.u-nav--rail .u-nav__item-text,
.u-nav--rail .v-badge,
.u-nav--rail .u-nav__resume {
  display: none;
}

/* Пункт «Продолжить» над списком секций. */
.u-nav__resume {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin: var(--sp-2) var(--sp-3) var(--sp-1);
  padding: var(--sp-2) var(--sp-2);
  border-radius: var(--radius-md);
  background: var(--c-primary-soft);
  color: var(--c-primary);
  text-decoration: none;
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  transition: background var(--dur-fast) var(--ease);
}

.u-nav__resume:hover {
  background: var(--c-primary-soft);
  filter: brightness(0.97);
}

.u-nav__resume-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.u-nav--rail .u-nav__item {
  justify-content: center;
}

.u-nav--rail .u-nav__item :deep(.v-list-item__prepend > .v-icon) {
  margin-inline-end: 0;
}

/* Закреплённые курсы — в rail не нужны. */
.u-nav--rail .u-nav__pinned-shell {
  display: none;
}

/* Закреплённые курсы. */
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
