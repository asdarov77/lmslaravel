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

      <v-list-group value="true">
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
          icon: "mdi-cloud-upload",
          title: this.$t("app.menu.files"),
          link: "/files/add",
          contentType: "",
        },
        {
          icon: "mdi-domain",
          title: this.$t("app.menu.categories"),
          link: "/categories",
          contentType: "",
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
          // Регресс: пункт вёл на /user/learning — такого маршрута в
          // Router/routes.js нет, клик открывал страницу 404. Теперь
          // ведёт на существующий group.learning, а заголовок переведён
          // в i18n вместо литерала "user learning".
          // Без id в конце: раньше был жёстко зашит /group/learning/1,
          // то есть из меню записать можно было только группу №1, и
          // непонятно было, какую именно. Теперь группу выбирают в форме.
          icon: "mdi-calendar",
          title: this.$t("app.menu.learning"),
          link: "/group/learning",
          contentType: " ",
        },
        {
          icon: "mdi-calendar",
          title: this.$t("app.menu.exams"),
          link: "/questions",
          contentType: "manage-users",
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
          icon: "mdi-account-multiple-outline",
          title: this.$t("app.menu.users"),
          link: "/user/list",
          contentType: "",
        },
        {
          icon: "mdi-account-plus",
          title: this.$t("app.menu.reguser"),
          link: "/reg",
          contentType: "manage-users",
        },
        {
          icon: "mdi-account-group-outline",
          title: this.$t("app.menu.groups"),
          link: "/groups/list",
          contentType: "",
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
        {
          // Регресс: подпись была "Курсы" — такой же, как у пункта
          // /courses/list. Оба элемента получали одинаковый id, и Vuetify
          // ругался «Multiple nodes with the same ID». Пункт ведёт на
          // личный кабинет (/auk), поэтому подпись «Мои курсы».
          icon: "mdi-cog-outline",
          title: this.$t("app.menu.mycourses"),
          link: "/auk",
          contentType: "",
        },
        
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