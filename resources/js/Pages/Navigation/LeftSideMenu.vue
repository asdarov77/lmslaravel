<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
    <v-list v-model:opened="open">
      <v-list-item
        v-for="(item, i) in menuItems"
        :key="i"
        :value="item.title"
        :title="item.title"
        :prepend-icon="item.icon"
        :to="item.link"
      >
      </v-list-item>

      <!--
        Группа скрывается, если все её пункты отфильтрованы по правам.
        Иначе у обучаемого остаётся заголовок «Управление пользователями»,
        который никуда не ведёт и выглядит как недогруженная страница.
      -->
      <v-list-group v-if="menuUsers.length" value="true">
        <template v-slot:activator="{ props }">
          <v-list-item
            v-bind="props"
            prepend-icon="mdi-account-circle"
            :title="$t('app.menu.userSection')"
          ></v-list-item>
        </template>

        <v-list-item
          v-for="(item, i) in menuUsers"
          :key="i"
          :value="item.title"
          :title="item.title"
          :prepend-icon="item.icon"
          :to="item.link"
        ></v-list-item>
      </v-list-group>
      <!-- <logout-app style="position: fixed; left: 0; right: 0; bottom: 0; z-index: 10;"></logout-app> -->
       <logout-app></logout-app>
    </v-list>
</template>

<script>
import LogoutApp from "./LogoutApp.vue";
import { mapGetters } from "vuex";
import routes from "../../Router/routes";

/**
 * Требования прав берутся ИЗ МАРШРУТОВ, а не из поля contentType
 * у пункта меню.
 *
 * Почему: раньше требование дублировалось в двух местах, и они
 * расходились. «Классы» и «Календарь» были помечены contentType:
 * "manage-users" (legacy-алиас users.view), тогда как маршруты
 * требовали content.manage и exams.manage|grading.manage. В итоге
 * инструктор видел пункт, клик давал 403, а администратор с
 * manage-users, но без exams.manage получал то же самое.
 *
 * Теперь единственный источник — meta.permission в Router/routes.js.
 * Изменили требование маршрута — меню обновилось само, и разойтись
 * они больше не могут.
 */
const ROUTE_PERMISSIONS = (() => {
  const index = new Map();

  const walk = (list) => {
    (list || []).forEach((route) => {
      if (route.path) {
        index.set(route.path, Array.isArray(route.meta?.permission) ? route.meta.permission : []);
      }
      if (route.children) walk(route.children);
    });
  };

  walk(routes);

  return index;
})();

/** Приводит ссылку к виду пути маршрута: без хэша и query. */
const toRoutePath = (link) => String(link || "").split("#").pop().split("?")[0] || "/";

/**
 * Требования для ссылки.
 *
 * Точное совпадение пути, иначе — самый длинный подходящий префикс:
 * пункт «/group/learning» ведёт на маршрут '/group/learning/:idEdit?'.
 * Путь без записи в индексе — требований нет (маршрут открыт всем).
 */
const requiredFor = (link) => {
  const path = toRoutePath(link);
  const exact = ROUTE_PERMISSIONS.get(path);

  if (exact) return exact;

  let best = null;

  for (const [routePath, required] of ROUTE_PERMISSIONS) {
    // Отбрасываем параметры: '/group/learning/:idEdit?' -> '/group/learning'.
    // Слэш в конце тоже убираем, иначе '/group/learning' не проходит
    // startsWith('/group/learning/') и пункт считался открытым для всех.
    const base = routePath
      .replace(/:[^/]+\??/g, "")
      .replace(/\/$/, "");

    if (base && path.startsWith(base) && (!best || base.length > best.length)) {
      best = base;
      ROUTE_PERMISSIONS.set(path, required);
    }
  }

  return best === null ? [] : ROUTE_PERMISSIONS.get(path);
};

export default {
  data: () => ({
    open: ["Users"],
  }),

  components: { LogoutApp },
  computed: {
    ...mapGetters("Auth", ["can"]),

    menuItems() {
      const items = [
        // {
        //   icon: "mdi-home",
        //   title: this.$t("app.about"),
        //   link: "/about",
        // },
        // {
        //   icon: "mdi-message-text",
        //   title: this.$t("app.menu.contacts"),
        //   link: "/contacts",
        // },
        {
          // Загрузка файлов — только тем, кто контент создаёт.
          // Требование берётся из маршрута /files/add:
          // files.upload или courses.manage.
          icon: "mdi-cloud-upload",
          title: this.$t("app.menu.files"),
          link: "/files/add",
        },
        {
          // Справочник категорий (специальностей) — методическая
          // страница. Раньше требований не было вовсе, поэтому
          // обучаемый видел «Категории» и все специальности, включая
          // те, на которые его не записывали. Теперь требование
          // categories.manage приходит из маршрута /categories.
          icon: "mdi-domain",
          title: this.$t("app.menu.categories"),
          link: "/categories",
        },
        {
          icon: "mdi-domain",
          title: this.$t("app.menu.courses"),
          link: "/courses/list",
        },
        {
          icon: "mdi-domain",
          title: this.$t("app.menu.classes"),
          link: "/classes",
        },
        {
          icon: "mdi-calendar",
          title: this.$t("app.menu.calendar"),
          link: "/calendar",
        },
        // {
        //   icon: "mdi-calendar",
        //   title: "filemanager",
        //   link: "/filemanager",
        // },
        {
          // Запись групп на курсы — методическая операция (users.courses).
          // Это НЕ «учебный план» обучаемого: см. пункт ниже.
          icon: "mdi-account-group-outline",
          title: this.$t("app.menu.learning"),
          link: "/group/learning",
          // contentType: " " (пробел) — не slug. visibleFor() делает
          // .trim(), получал пустую строку и показывал пункт ВСЕМ,
          // включая обучаемого, которому маршрут отдавал 403.
        },
        {
          // Учебный план обучаемого: его собственные курсы и материалы.
          // До этого пункта не существовало — обучаемый не видел ни одного
          // назначенного ему курса, а «Учебный план» в меню вёл на
          // админскую форму и отдавал 403.
          icon: "mdi-calendar-month-outline",
          title: this.$t("app.menu.myLearning"),
          link: "/my/learning",
        },
        {
          // Личный кабинет (дашборд): показатели, дедлайны, экзамены.
          icon: "mdi-view-dashboard-outline",
          title: this.$t("app.menu.dashboard"),
          link: "/dashboard",
        },
        {
          // Экзамены. Обучение экзамену (exams.take) есть и у
          // обучаемого, поэтому пункт нельзя прятать за manage-users —
          // иначе обучаемый не может пройти назначенный ему экзамен.
          //
          // Ведёт на /my/exams (список назначенного), а не сразу на
          // страницу прохождения: раньше пункт открывал форму, где
          // вопросы выбирались по модулю в адресной строке, и обучаемый
          // не видел, что ему вообще назначено.
          icon: "mdi-clipboard-check-outline",
          title: this.$t("app.menu.exams"),
          link: "/my/exams",
        },
        {
          icon: "mdi-calendar",
          title: this.$t("app.menu.questionbank"),
          link: "/questions-main",
        },
      ]
      
      return items.filter(menu => this.visibleFor(menu));
    },

    menuUsers() {
      const items = [
        {
          // Раньше требований не было, поэтому «Пользователи» были
          // видны обучаемому, хотя /user/list закрыт правом users.view.
          // Теперь users.view приходит из маршрута /user/list.
          icon: "mdi-account-multiple-outline",
          title: this.$t("app.menu.users"),
          link: "/user/list",
        },
        {
          // Регистрация/создание пользователя. Маршрут /reg открыт всем
          // (регистрация публична), но назначить роль и группу может
          // только users.create — поэтому требование задано здесь, а не
          // взято из маршрута. Раньше стоял legacy-алиас manage-users,
          // который закрывал пункт от инструктора с users.create.
          icon: "mdi-account-plus",
          title: this.$t("app.menu.reguser"),
          link: "/reg",
          permission: ["users.create"],
        },
        {
          // Управление группами — раньше тоже требований не было.
          // Теперь groups.view|users.view приходят из /groups/list.
          icon: "mdi-account-group-outline",
          title: this.$t("app.menu.groups"),
          link: "/groups/list",
        },
        {
          // Управление правами вынесено из списка пользователей
          // в отдельную страницу. Видно администратору
          // (users.permissions) и инструктору (users.view).
          icon: "mdi-shield-key-outline",
          title: this.$t("app.menu.permissions"),
          link: "/permissions",
        },
        // Пункт «Мои курсы» (/auk) убран: это та же личная страница,
        // что и /dashboard, и два пункта об одном и том же в меню —
        // лишнее. Ссылка на /auk остаётся рабочей (маршрут не удалён).
      ]
      
      return items.filter(menu => this.visibleFor(menu));
    },
  },

  methods: {
    // ВАЖНО: это метод, а НЕ computed. Раньше visibleFor был объявлен в
    // computed, и Vue вызывал его как getter, передавая первым
    // аргументом сам экземпляр компонента — внутри шло
    // `contentType.trim()` => "TypeError: e.trim is not a function", и
    // всё боковое меню падало (в том числе у администратора).
    //
    // Логика совпадает с роутер-гардом (Router/index.js) и бэкенд-
    // middleware: пункт без требований виден всем, иначе достаточно
    // ОДНОГО из прав (OR). Геттер can понимает и старые slug'и
    // (manage-users), и новые (users.view) через алиасы каталога.

    /**
     * Виден ли пункт текущему пользователю.
     *
     * Требование берётся из meta.permission маршрута-цели. Пункт может
     * объявить своё поле permission, если он СТРОЖЕ маршрута: так
     * сделано для «Регистрации» (/reg) — сама страница открыта всем,
     * потому что регистрация публична, но назначать роль и группу может
     * только users.create, и показывать пункт нужно именно этому.
     *
     * Проверка — через геттер can (единая точка проверки прав).
     */
    visibleFor(item) {
      const link = typeof item === 'string' ? item : item.link;
      const own = typeof item === 'string' ? null : item.permission;
      const required = (own || requiredFor(link)).filter(Boolean);

      if (required.length === 0) return true;

      // Именно can(...required), а НЕ can(required): массив первым
      // аргументом у can() означает «все права обязательны» (AND), а
      // список из meta.permission — это альтернативы через запятую (OR),
      // как в middleware `permission:a,b` на бэкенде. С массивом под
      // видом AND пункт скрывался бы и у того, кому право выдано.
      return this.can(...required);
    },
  },
};
</script>