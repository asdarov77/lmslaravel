<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
  
  <!--
    Класс app--course включает «учебный» режим: на странице курса
    шапка и футер скрываются через CSS, а не через v-show. Раньше
    v-show="courseItemShow" прятал их по флагу, и при переходе на
    страницу курса они мигали. Теперь это вариант раскладки.
  -->
  <v-app :class="{ 'app--course': isCourseView }"
    >
    <v-app-bar color="primary" prominence="prominent">
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

      <notification-bell class="app__bell" />

      <theme-toggle class="app__theme" />

      <!--
        Переключатель плотности: компактный/обычный.
        Раньше плотность была зашита в токенах, и пользователь не мог
        её изменить. Теперь есть переключатель в шапке.
      -->
      <v-btn
        :icon="density === 'compact' ? 'mdi-view-compact' : 'mdi-view-compact-outline'"
        :aria-label="$t('common.density')"
        variant="text"
        size="small"
        @click="toggleDensity"
      />

      <!--~~~~ профиль пользователя ~~~~-->
      <account-menu> </account-menu>
    </v-app-bar>
   

    <!--
      Боковое меню.

      Ширина — из токена --layout-drawer-width, а не число в разметке.
      На узких экранах меню становится ВРЕМЕННЫМ (temporary): постоянная
      панель шириной 300px на телефоне съедала половину экрана и
      закрывала собой контент.

      Состояние «открыто/закрыто» запоминается: пользователю не нужно
      заново открывать меню после каждой перезагрузки страницы.

      Проп bottom здесь стоял, но в этой версии Vuetify у панели такого
      пропа нет (есть location), и он попадал в разметку просто как
      атрибут, то есть ничего не делал. Настоящая причина того, что меню
      лежало поверх содержимого, — не число, а тип ширины: см.
      drawerWidth ниже.
    -->
    <v-navigation-drawer
      app
      v-model="drawer"
      v-if="loggedIn && courseItemManiShow"
      :permanent="!isNarrow"
      :temporary="isNarrow"
      :width="drawerWidth"
      class="app__drawer"
    >
      <left-side-menu v-if="loggedIn "></left-side-menu>
    </v-navigation-drawer>
    <v-main>
      <!--
        Скелетон загрузки.
        
        Раньше пока грузились данные, страница оставалась пустой — это
        выглядело как «приложение зависло». Теперь вместо пустоты
        рисуется скелетон с пульсацией.
      -->
      <div v-if="isLoading" class="app__skeleton" data-test="app-skeleton">
        <div class="app__skeleton-bar"></div>
        <div class="app__skeleton-line app__skeleton-line--w80"></div>
        <div class="app__skeleton-line"></div>
        <div class="app__skeleton-line app__skeleton-line--w60"></div>
      </div>
      <v-container v-else fluid class="app__content">
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
    <v-footer app bottom fixed padless width="100%" class="app__footer">
      <div class="app__footer-inner">
        <!--
          Год берётся из текущего года, а не зашит в разметку: раньше он
          стоял статично и устаревал.
        -->
        <p class="app__copyright">&copy; {{ currentYear }} Dinamika</p>
        <!--
          Переключатель языка в футере.

          Раньше здесь стояли две кнопки с голым текстом «eng/rus»:
          без подписи для скринридера, без отметки текущего языка и
          без единого вида с остальным интерфейсом.

          Теперь это те же значки, что и в LanguageSelector на странице
          входа, но кнопками, а не выпадающим списком: в ФИКСИРОВАННОМ
          футере список не открывается (проверено в браузере — клик по
          select не даёт меню, и переключить язык из футера нечем).
          aria-pressed показывает, какой язык активен сейчас.
        -->
        <div class="app__langs" role="group" :aria-label="$t('common.language')">
          <button
            type="button"
            class="lang-badge"
            :class="{ 'lang-badge--on': language === 'ru' }"
            aria-pressed="true"
            :aria-label="'Русский'"
            @click="changeRu"
          >RU</button>
          <button
            type="button"
            class="lang-badge"
            :class="{ 'lang-badge--on': language === 'en' }"
            :aria-pressed="language === 'en'"
            :aria-label="'English'"
            @click="changeEn"
          >EN</button>
        </div>
      </div>
    </v-footer>
  </v-app>
    <!--
      Стек уведомлений живёт в корне приложения, а не на страницах.

      Пока тост был частью компонента страницы, он умирал вместе с
      ней: переход на другую страницу убирал сообщение, и результат
      действия («Сохранено») пользователь не успевал увидеть. Здесь
      сообщения переживают навигацию.
    -->
    <ToastStack />
</template>

<script>
import { mapGetters, mapState } from 'vuex';
import LeftSideMenu from './Pages/Navigation/LeftSideMenu.vue';
import AccountMenu from './Pages/Navigation/AccountMenu.vue';
import Breadcrumbs from './components/ui/Breadcrumbs.vue';
import GlobalSearch from './components/ui/GlobalSearch.vue';
import ThemeToggle from './components/ui/ThemeToggle.vue';
import AppBrand from './components/ui/AppBrand.vue';
import ToastStack from './components/ui/ToastStack.vue';
import NotificationBell from './components/ui/NotificationBell.vue';


/**
 * Читает сохранённое состояние меню.
 *
 * Отдельная функция, а не обращение в data(): при недоступном
 * хранилище (приватный режим, sandbox) обращение бросило бы исключение
 * прямо при создании компонента и не отрисовалось бы всё приложение.
 */
function readDrawer() {
  try {
    const saved = window.localStorage.getItem('ui-drawer');

    return saved === null ? true : saved === '1';
  } catch (e) {
    return true;
  }
}

export default {
  components: { AppBrand, Breadcrumbs, GlobalSearch, ThemeToggle, ToastStack, NotificationBell },
  mounted() {
    const hist = this.$router.options.history;

    // Ширина окна в состоянии обновляется здесь (см. viewportWidth в
    // data). Событие снимается в beforeUnmount, иначе при уходе со
    // страницы слушатель остаётся висеть на window.
    this._onResize = () => {
      this.viewportWidth = window.innerWidth;
    };
    window.addEventListener("resize", this._onResize);

    /*
     * Снимаем скелетон после первой отрисовки.
     *
     * Раньше isLoading объявлялся и не использовался: страница оставалась
     * пустой, пока грузились данные. Теперь скелетон показывается, пока
     * приложение не отрисуется, и снимается после первой отрисовки.
     */
    this.$nextTick(() => {
      this.isLoading = false;
    });
  },

  beforeUnmount() {
    if (this._onResize) {
      window.removeEventListener("resize", this._onResize);
      this._onResize = null;
    }
  },

  methods: {
    changeRu() {
      this.$store.commit("Ui/SET_LANGUAGE", "ru");
      this.$i18n.locale = "ru";
    },
    changeEn() {
      this.$store.commit("Ui/SET_LANGUAGE", "en");
      this.$i18n.locale = "en";
    },

    checkLeftSideMenu(){
      // Раньше здесь было сравнение user.role !== 'Обучаемый' по строке.
      // Оно ломалось дважды: у обучаемого роль хранится как 'trainee'
      // в части записей (сравнение давало true и показывало меню),
      // а у пользователя с ролью из role_user колонка role пуста —
      // то есть проверка читала несуществующее поле.
      return this.loggedIn && !this.isTrainee;
    },

  },

  name: "App",
  data: () => ({
    /*
     * Состояние меню берётся из LocalStorage: см. watcher drawer.
     * При отсутствии значения (первый визит) меню открыто, как раньше.
     */
    drawer: readDrawer(),
    tab: true,
    courseItemShow: false,
    courseItemManiShow: false,
    /*
     * Флаг загрузки приложения.
     *
     * Объявлялся и не использовался: пока грузились данные, страница
     * оставалась пустой, и это выглядело как «приложение зависло».
     * Теперь по нему рисуется скелетон (см. шаблон).
     */
    isLoading: true,

    /*
     * Ширина окна — в состоянии, а не вычисляется прямо из matchMedia.
     *
     * Раньше `isNarrow` был computed без единой реактивной зависимости:
     * он читал window.matchMedia и зависел только от неё. Vue
     * вычислял его ОДИН раз и больше не пересчитывал, потому что
     * изменение размера окна не отслеживал никто. Следствие: открыли
     * страницу на широком экране, сузили окно или открыли на телефоне —
     * меню оставалось постоянным и резервировало 300px из 500px
     * экрана, то есть на телефоне уезжало почти всё место. Теперь
     * размер приходит из состояния, который обновляется по событию
     * resize (см. onResize), и вычисление честно пересчитывается.
     */
    viewportWidth: typeof window === 'undefined' ? 1280 : window.innerWidth,
  }),
  components: { LeftSideMenu, AccountMenu },

  //----------сохранение в session storage----------------

  computed: {
    /*
     * Открыт ли учебный экран курса.
     *
     * На странице курса шапка и футер не нужны: это полноэкранный
     * просмотр материала. Раньше они скрывались через v-show по флагу,
     * и при переходе мигали. Теперь это вариант раскладки.
     */
    isCourseView() {
      return (
        this.$route?.name === 'courses.item' ||
        this.$route?.name === 'courses.itemmani'
      );
    },

    /*
     * Узкий экран — это телефон/планшет. На нём меню должно
     * перекрывать контент по требованию, а не вытеснять его.
     */
    isNarrow() {
      return this.viewportWidth <= 900;
    },

    /**
     * Ширина панели меню — ЧИСЛОМ, и это не stylistic выбор.
     *
     * Vuetify считает ширину как `Number(props.width)`
     * (VNavigationDrawer.js). Строка "300px" даёт `Number("300px")` =
     * NaN, и NaN уходит в layout: v-main получал
     * `--v-layout-left: NaN` и НЕ резервировал место под меню. Панель
     * при этом оставалась слева, а содержимое начиналось с нуля — левое
     * меню лежало поверх заголовков, хлебных крошек и кнопок, и по ним
     * нельзя было нажать. Симптом выглядел как «страница сломана» и
     * «тест не находит кнопку», хотя разметка была правильной.
     *
     * Поэтому токен читается и сразу переводится в число. Значение
     * по умолчанию продублировано на случай, если токен не задан.
     */
    drawerWidth() {
      const name = this.isNarrow
        ? "--layout-drawer-width-mobile"
        : "--layout-drawer-width";

      const raw = getComputedStyle(document.documentElement).getPropertyValue(name);
      const px = Number.parseFloat(raw);

      return Number.isFinite(px) && px > 0 ? px : 300;
    },

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

