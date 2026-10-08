<template>
  <!--
    Навигация обёрнута в <nav> намеренно.

    Без него список пунктов не является навигационной областью: скринридер
    не может её перечислить как «навигацию», а тесты, ищущие пункты по
    `nav .v-list-item`, получали пустой массив и падали на ровном месте.
    Проп `nav` у v-list даёт только класс оформления, элемент <nav> не
    появляется.
  -->
  <nav :aria-label="$t('app.menu.main')" class="u-nav">
     <v-list density="compact" nav>
      <template v-for="section in visibleSections" :key="section.key">
        <v-list-subheader
          v-if="section.showHeader"
          class="u-nav__subheader"
          data-test="nav-section"
        >
          {{ section.title }}
        </v-list-subheader>

        <!--
          Активность пункта и индикатор-полоса описаны в стилях ниже.
          Раньше активный пункт отличался только синим текстом: на
          плотном списке его было не найти взглядом.
        -->
        <v-list-item
          v-for="item in section.items"
          :key="item.key"
          :to="item.link"
          :prepend-icon="item.icon"
          :title="item.title"
          :active="isActive(item)"
          class="u-nav__item"
          color="primary"
          data-test="nav-item"
        />
      </template>
    </v-list>
  </nav>
</template>

<script>
import { mapGetters } from 'vuex'
import { navigationSections } from '../../navigation'
import { routePermissionIndex, requiredPermissions } from '../../utils/routePermissions'

/**
 * Боковое меню.
 *
 * Пункты и группировка приходят из `navigation.js`, а права — из
 * `meta.permission` маршрутов (utils/routePermissions).
 *
 * Почему требования берутся из маршрутов, а не из пункта меню: они
 * описывают одно и то же право, и раньше они были продублированы в двух
 * местах. «Классы» и «Календарь» были помечены contentType:
 * "manage-users", тогда как маршруты требовали content.manage и
 * exams.manage — инструктор видел пункт, клик давал 403, а администратор
 * с manage-users, но без exams.manage, получал то же самое. Теперь
 * источник один: изменили требование маршрута — меню обновилось само.
 *
 * Секции скрываются целиком, когда в них не осталось доступных пунктов,
 * а заголовки показываются, когда секций больше одной.
 */
export default {
  data: () => ({
    requiredBy: routePermissionIndex(),
  }),

  computed: {
    ...mapGetters('Auth', ['can']),

    /** Пункты секции после отсева по правам. */
    filterItems() {
      return (items) =>
        items
          .filter((item) => this.allows(item.link))
          .filter((item) => this.fitsRole(item))
          .map((item) => ({
            ...item,
            title: this.$t(item.titleKey),
          }))
    },

    /*
     * Виден ли пункт именно этой категории пользователя.
     *
     * Единственное место в меню, где видимость выводится не из
     * meta.permission маршрута. Причина: /my/learning открыт любому
     * вошедшему — это его собственные записи на курсы, — но методисту
     * показывать нечего: записей у него нет, и страница открывается пустой.
     * Права тут ни при чём, поэтому и вывести условие из meta.permission
     * маршрута нельзя: маршрут доступен всем авторизованным намеренно.
     *
     * Признак «управляющий» — по правам, а не по строке роли: роли
     * приходят из двух источников и в разных базах называются по-разному.
     */
    isManager() {
      return this.can(
        'users.view',
        'users.permissions',
        'groups.view',
        'groups.manage',
        'courses.manage',
      )
    },

    /**
     * Секции после отсева.
     *
     * Пустые секции убираются целиком: у обучаемого иначе остались бы
     * заголовки «Методический кабинет» и «Управление» без единого пункта.
     *
     * Заголовки показываются, только когда секций больше одной. Раньше
     * правило было «скрывать секцию с одним пунктом», и такой пункт
     * ВИЗУАЛЬНО попадал в предыдущую секцию: обучаемый видел «Новый
     * пользователь» под заголовком «Обучение». Секция из одного пункта
     * остаётся на своём месте — иначе она врёт о принадлежности.
     */
    visibleSections() {
      const sections = navigationSections
        .map((section) => ({
          key: section.key,
          title: this.$t(`app.nav.${section.key}`),
          items: this.filterItems(section.items),
        }))
        .filter((section) => section.items.length > 0)

      const showHeaders = sections.length > 1

      return sections.map((section) => ({ ...section, showHeader: showHeaders }))
    },

  },

  methods: {
    /**
     * Виден ли пункт именно этой категории пользователя.
     *
     * Именно метод, а не computed: computed в Vue не принимает аргументов
     * и кэширует результат по ПЕРВОМУ вызову. Объявленное как computed
     * fitsRole(item) вычислялось один раз для первого пункта меню и
     * возвращало его же решение всем остальным — у обучаемого и
     * инструктора меню оказывалось пустым.
     */
    /**
     * Активен ли пункт.
     *
     * Сравнение по startsWith, а не по равенству: пункт «Пользователи»
     * ведёт на /user/list, а открытая страница редактирования имеет
     * адрес /user/edit/7. При точном сравнении пункт терял подсветку
     * ровно тогда, когда пользователь вглубь раздела.
     */
    isActive(item) {
      const current = this.$route?.path || "";

      if (!current || !item?.link) return false;

      if (current === item.link || current.startsWith(item.link + "/")) {
        return true;
      }

      /*
       * Поддерево раздела шире самой ссылки: /user/edit/7 — это тоже
       * «Пользователи», хотя адрес не начинается с /user/list. Для
       * таких пунктов в navigation.js задан activeMatch — префикс
       * раздела. Без него пункт терял подсветку ровно тогда, когда
       * пользователь ушёл вглубь раздела.
       */
      return Boolean(item.activeMatch) && current.startsWith(item.activeMatch + "/");
    },

    fitsRole(item) {
      const only = item?.onlyFor

      if (!only) return true

      return only === 'manager' ? this.isManager : !this.isManager
    },

    /**
     * Есть ли право открыть ссылку.
     *
     * requiredPermissions, а не прямой доступ по индексу: пункт
     * «/group/learning» ведёт на маршрут '/group/learning/:idEdit?' и без
     * поиска по префиксу считался бы открытым для всех.
     */
    allows(link) {
      const required = requiredPermissions(this.requiredBy, link)

      if (!required || required.length === 0) {
        return true
      }

      return this.can(...required)
    },
  },
}
</script>

<style scoped>
.u-nav__subheader {
  font-size: 0.72rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  opacity: 0.62;
}

/*
 * Пункт навигации.
 *
 * Индикатор активного пункта сделан box-shadow, а не border-left:
 * у v-list-item своя рамка, и border с нулевой шириной перебивался
 * стилями Vuetify — полоса просто не появлялась (проверено в браузере:
 * вычисленная ширина была 0px). inset-тень не конфликтует ни с чем.
 */
.u-nav__item {
  box-shadow: inset 3px 0 0 transparent;
}

/* Наведение и активное состояние — из токенов, а не «как получится». */
.u-nav__item:hover {
  background-color: var(--c-primary-soft);
}

/*
 * Активный пункт.
 *
 * Индикатор-полоса сбоку — устойчивее смены цвета текста: она видна
 * и при плохом контрасте, и дальтонизме. Цвет текста оставлен
 * синим по умолчанию Vuetify (color="primary" на v-list-item).
 */
.u-nav__item.v-list-item--active {
  background-color: var(--c-primary-soft);
  box-shadow: inset 3px 0 0 var(--c-primary);
}
</style>