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
import { mapState, mapGetters } from "vuex";
export default {
  data: () => ({
    open: ["Users"],
  }),

  components: { LogoutApp },
  computed: {
    ...mapState('Auth', ['accessToken', 'user']),    
    ...mapGetters("Auth", ["hasPermission"]),
    hasEditPermission() {
      return this.hasPermission(["manage-users"], "Manage users");
    },

    menuItems() {
      const items = [
        // {
        //   icon: "mdi-home",
        //   title: this.$t("app.about"),
        //   link: "/about",
        //   contentType: "",
        // },
        // {
        //   icon: "mdi-message-text",
        //   title: this.$t("app.menu.contacts"),
        //   link: "/contacts",
        //   contentType: "",
        // },
        {
          // Загрузка файлов — только тем, кто контент создаёт.
          // contentType: "" делал пункт видимым ВСЕМ, включая
          // обучаемого, который по этому пункту получал 403.
          icon: "mdi-cloud-upload",
          title: this.$t("app.menu.files"),
          link: "/files/add",
          contentType: "content.manage",
        },
        {
          // Справочник категорий (специальностей) — методическая
          // страница. Раньше стоял contentType: "", поэтому обучаемый
          // видел «Категории» и все специальности, включая те,
          // на которые его не записывали.
          icon: "mdi-domain",
          title: this.$t("app.menu.categories"),
          link: "/categories",
          contentType: "categories.manage",
        },
        {
          icon: "mdi-domain",
          title: this.$t("app.menu.courses"),
          link: "/courses/list",
          contentType: "",
        },
        {
          icon: "mdi-domain",
          title: this.$t("app.menu.classes"),
          link: "/classes",
          contentType: "manage-users",
        },
        {
          icon: "mdi-calendar",
          title: this.$t("app.menu.calendar"),
          link: "/calendar",
          contentType: "manage-users",
        },
        // {
        //   icon: "mdi-calendar",
        //   title: "filemanager",
        //   link: "/filemanager",
        //   contentType: "manage-users",
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
          contentType: "users.courses",
        },
        {
          // Учебный план обучаемого: его собственные курсы и материалы.
          // До этого пункта не существовало — обучаемый не видел ни одного
          // назначенного ему курса, а «Учебный план» в меню вёл на
          // админскую форму и отдавал 403.
          icon: "mdi-calendar-month-outline",
          title: this.$t("app.menu.myLearning"),
          link: "/my/learning",
          contentType: "",
        },
        {
          // Личный кабинет (дашборд): показатели, дедлайны, экзамены.
          icon: "mdi-view-dashboard-outline",
          title: this.$t("app.menu.dashboard"),
          link: "/dashboard",
          contentType: "",
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
          contentType: ["exams.take", "exams.manage"],
        },
        {
          icon: "mdi-calendar",
          title: this.$t("app.menu.questionbank"),
          link: "/questions-main",
          contentType: "manage-users",
        },
      ]
      
      return items.filter(menu => this.visibleFor(menu.contentType));
    },

    menuUsers() {
      const items = [
        {
          // contentType: "" показывал «Пользователей» обучаемому,
          // хотя /user/list закрыт правом users.view.
          icon: "mdi-account-multiple-outline",
          title: this.$t("app.menu.users"),
          link: "/user/list",
          contentType: "users.view",
        },
        {
          icon: "mdi-account-plus",
          title: this.$t("app.menu.reguser"),
          link: "/reg",
          contentType: "manage-users",
        },
        {
          // Управление группами — тоже contentType: "" было.
          icon: "mdi-account-group-outline",
          title: this.$t("app.menu.groups"),
          link: "/groups/list",
          contentType: ["groups.view", "groups.manage"],
        },
        {
          // Управление правами вынесено из списка пользователей
          // в отдельную страницу. contentType — массив: пункт виден
          // администратору (users.permissions) и инструктору (users.view).
          icon: "mdi-shield-key-outline",
          title: this.$t("app.menu.permissions"),
          link: "/permissions",
          contentType: ["users.permissions", "users.view"],
        },
        // Пункт «Мои курсы» (/auk) убран: это та же личная страница,
        // что и /dashboard, и два пункта об одном и том же в меню —
        // лишнее. Ссылка на /auk остаётся рабочей (маршрут не удалён).
      ]
      
      return items.filter(menu => this.visibleFor(menu.contentType));
    },
  },

  // ВАЖНО: это метод, а НЕ computed.
  // Раньше visibleFor был объявлен в computed, и Vue вызывал его как
  // getter, передавая первым аргументом сам экземпляр компонента.
  // Внутри шло `contentType.trim()` => "TypeError: e.trim is not a function",
  // и всё боковое меню падало (в том числе у администратора).
  methods: {
    /**
     * Единый предиктор видимости пункта меню. Логика совпадает с
     * роутер-гардом (Router/index.js) и бэкенд-middleware: пункт без
     * требований виден всем, иначе достаточно ОДНОГО из прав (OR).
     * hasPermission в сторе понимает и старые slug'и (manage-users),
     * и новые (users.view) через алиасы каталога.
     */
    visibleFor(contentType) {
      // Приводим к строке: в пункт меню может попасть массив slug'ов
      // или неожиданный тип из конфигурации.
      const raw = Array.isArray(contentType)
        ? contentType.filter(Boolean).map(v => String(v).trim()).filter(Boolean)
        : (contentType == null ? [] : [String(contentType).trim()]).filter(Boolean);

      if (raw.length === 0) return true;

      return this.hasPermission(raw);
    },
  },
};
</script>