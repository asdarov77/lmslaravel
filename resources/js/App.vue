<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
  
  <v-app
    >
    <v-app-bar color="primary" prominence="prominent" v-show="courseItemShow">
      <v-app-bar-nav-icon
        v-if="checkLeftSideMenu()"
        variant="text"
        @click.stop="drawer = !drawer"
      ></v-app-bar-nav-icon>
      <!-- tag="span" // in app-bar-title ?-->
      <v-app-bar-title>
        <router-link to="/" class="app__brand" :aria-label="$t('app.title')">
          <app-brand :full="true" />
        </router-link>
      </v-app-bar-title>
      <!--
          Глобальный поиск. Скрыт для гостя: /api/search требует
          авторизации, и кнопка без смысла только вводила бы в заблуждение.
      -->
      <global-search v-if="loggedIn" class="app__search" />

      <theme-toggle class="app__theme" />

      <!--~~~~ профиль пользователя ~~~~-->
      <account-menu> </account-menu>
    </v-app-bar>
   

    <v-navigation-drawer
      app
      v-model="drawer"
      v-if="loggedIn && courseItemManiShow"
      bottom
      permanent
      width="300"
    >
      <left-side-menu v-if="loggedIn "></left-side-menu>
    </v-navigation-drawer>
    <v-main>
      <v-container fluid class="app__content">
        <!--
            Крошки живут в каркасе, а не в каждой странице: уровни
            объявлены в meta маршрута, и раздел не должен угадывать
            структуру сам. На страницах без meta.breadcrumbs компонент
            ничего не рисует.
        -->
        <breadcrumbs />
        <router-view></router-view>
      </v-container>
    </v-main>
    <!--
        Футер собран на классах дизайн-системы. Раньше здесь стояли
        `container mx-auto flex justify-between items-center` — это
        Tailwind и Bulma одновременно, и ни того, ни другого в проекте
        нет: строка копирайта и переключатель языка просто стояли
        друг под другом без всякой раскладки.
    -->
    <v-footer app bottom fixed padless width="100%" class="app__footer" v-show="courseItemShow">
      <div class="app__footer-inner">
        <p class="app__copyright">&copy; 2023 Dinamika</p>
        <div class="app__langs">
          <v-btn size="x-small" variant="text" @click="changeEn">eng</v-btn>
          <v-btn size="x-small" variant="text" @click="changeRu">rus</v-btn>
        </div>
      </div>
    </v-footer>
  </v-app>
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import LeftSideMenu from './Pages/Navigation/LeftSideMenu.vue';
import AccountMenu from './Pages/Navigation/AccountMenu.vue';
import Breadcrumbs from './components/ui/Breadcrumbs.vue';
import GlobalSearch from './components/ui/GlobalSearch.vue';
import ThemeToggle from './components/ui/ThemeToggle.vue';
import AppBrand from './components/ui/AppBrand.vue';


export default {
  components: { AppBrand, Breadcrumbs, GlobalSearch, ThemeToggle },
  mounted() {
    //console.log("mounted");
    const hist = this.$router.options.history;

  },
  methods: {
    checkLeftSideMenu(){
      // Раньше здесь было сравнение user.role !== 'Обучаемый' по строке.
      // Оно ломалось дважды: у обучаемого роль хранится как 'trainee'
      // в части записей (сравнение давало true и показывало меню),
      // а у пользователя с ролью из role_user колонка role пуста —
      // то есть проверка читала несуществующее поле.
      return this.loggedIn && !this.isTrainee;
    },
    changeRu() {
      this.$store.commit("Ui/SET_LANGUAGE", "ru");
      this.$i18n.locale = "ru";
    },
    changeEn() {
      this.$store.commit("Ui/SET_LANGUAGE", "en");
      this.$i18n.locale = "en";
    },
  },

  name: "App",
  data: () => ({
    drawer: true,
    tab: true,
    courseItemShow: false,
    courseItemManiShow: false,
    isLoading: true, // состояние загрузки
  }),
  components: { LeftSideMenu, AccountMenu },

  //----------сохранение в session storage----------------

  computed: {
    ...mapGetters("Auth", ["loggedIn", "isTrainee"]),
    ...mapState("Ui", ["menudrawler", "language"]),
    ...mapState("Auth", ["user"]),

  },
watch: {
  '$route' (){
    if(this.$route.name === 'courses.item') this.drawer=false;
    if(this.$route.name !== "courses.item") this.courseItemShow=true;
    if(this.$route.name !== 'courses.itemmani') this.courseItemManiShow=true;
  },
}  
};

</script>

