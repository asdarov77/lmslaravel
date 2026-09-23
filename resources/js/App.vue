<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
  <!-- <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"
  /> -->

  <!-- <link href="//cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet" /> -->
  <v-app
    >
    <v-app-bar app color="primary" prominent v-show='courseItemShow'>
      <v-app-bar-nav-icon
        v-if="checkLeftSideMenu()"
        variant="text"
        @click.stop="drawer = !drawer"
      ></v-app-bar-nav-icon>
      <!-- tag="span" // in app-bar-title ?-->
      <v-app-bar-title>
        <router-link
          to="/"
          style="cursor: pointer; color: white; text-decoration: none"
          >{{ $t("app.title") }}
        </router-link>
      </v-app-bar-title>
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
      <v-container fluid>
        <router-view></router-view>
      </v-container>
    </v-main>
    <v-footer app bottom fixed padless width="100%" style="z-index: 999"  v-show='courseItemShow'
    >      
      <div class="container mx-auto flex justify-between items-center">      
        <p>&copy; 2023 Dinamika</p>            
    </div>
      <v-col class="text-right">
        <v-btn size="x-small" @click="changeEn" class="right">eng</v-btn>
        <v-btn size="x-small" @click="changeRu" class="right">rus</v-btn>
      </v-col>
    </v-footer>
  </v-app>
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import LeftSideMenu from './Pages/Navigation/LeftSideMenu.vue';
import AccountMenu from './Pages/Navigation/AccountMenu.vue';


export default {
  mounted() {
    //console.log("mounted");
    const hist = this.$router.options.history;

  },
  methods: {
    checkLeftSideMenu(){
      if (
        this.loggedIn && this.user.role !== 'Обучаемый') {
        return true
      }
      else {
        {
          return false
        }
      }
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
    ...mapGetters("Auth", ["loggedIn"]),
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

