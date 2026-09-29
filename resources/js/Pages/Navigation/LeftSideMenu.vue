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
            title="Пользователи"
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

    // Единый предиктор видимости пункта меню. Логика совпадает с
    // роутер-гардом (Router/index.js) и бэкенд-middleware: пункт без
    // требований виден всем, иначе достаточно ОДНОГО из прав (OR).
    // hasPermission в сторе понимает и старые slug'и (manage-users),
    // и новые (users.view) через алиасы каталога.
    visibleFor(contentType) {
      if (!contentType || !contentType.trim()) return true;
      const list = Array.isArray(contentType) ? contentType : [contentType];
      return this.hasPermission(list);
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
          icon: "mdi-calendar",
          title: "user learning",
          link: "/user/learning",
          contentType: " ",
        },
        {
          icon: "mdi-calendar",
          title: "Экзамен",
          link: "/questions",
          contentType: "manage-users",
        },
        {
          icon: "mdi-calendar",
          title: "Банк вопросов",
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
          icon: "mdi-cog-outline",
          title: this.$t("app.menu.courses"),
          link: "/auk/",
          contentType: "",
        },
        
      ]
      
      return items.filter(menu => this.visibleFor(menu.contentType));
    },
  },
};
</script>