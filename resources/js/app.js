
import './bootstrap.js';

// Порядок подключения важен: сначала библиотека, потом приложение.
//
// Раньше app.css шёл ДО vuetify/styles, и при равной специфичности
// выигрывал Vuetify. Например `.cats-chip--on { color:
// var(--c-on-primary) }` перебивался правилом `.v-btn`, и в тёмной
// теме чип оставался белым на светло-синем фоне (контраст 2.46 при
// норме 4.5). Теперь наши стили подключаются последними.
import 'vuetify/styles';

// Токены и базовые классы подключаются один раз на всё приложение.
// До этого app.css был закомментирован в vite.config.mjs, и шаблоны
// подтягивали его через @import внутри <style> — из-за чего стили
// приезжали в одни страницы и не приезжали в другие.
import '../css/tokens.css';
import '../css/app.css';
import { createApp, watch } from 'vue';

//import { createVuetify } from 'vuetify'
// import * as Vuex from 'vuex';
// import * as VueRouter from 'vue-router';
import Vuelidate from 'vuelidate'

import router from './Router';
import store from './Store';
// import store  from './Store/test.js';
import vuetify from './Vuetify';
import canDirective from './plugins/can.directive';



import App from './App.vue';
import { languages } from './locales';
import { defaultLocale } from './locales';
import { createI18n, useI18n } from 'vue-i18n';
import theme from './utils/theme';



// import { vue } from 'laravel-mix';
const messages = Object.assign(languages)

const i18n = createI18n({
  legacy: false,
  locale: defaultLocale,
  fallbackLocale: 'en',
  messages
})
//Vue.component('example-component', require('./components/ExampleComponent.vue'));
//Vue.component('prop-component', require('./components/PropComponent.vue'));
const app = createApp(App, {
  setup() {
    const { t } = useI18n()
    return { t }
  }
})
  .use(Vuelidate)
  .use(router)
  .use(vuetify)
  .use(store)
  .use(i18n)

// v-can — декларативная проверка прав (подробности в plugins/can.directive.js):
//   v-can="'users.view'"                      — одно право
//   v-can="['users.view','manage-users']"     — достаточно ЛЮБОГО (OR)
//   v-can.all="['a','b']"                     — нужны ВСЕ (AND)
// Элемент скрывается через display:none и возвращается, когда права
// приходят из GET /api/v1/me, поэтому элемент не «исчезает навсегда».
// v-permission — старый алиас той же директивы (обратная совместимость).
app.directive('can', canDirective);
app.directive('permission', canDirective);

/**
 * Заголовок документа собирается из раздела и названия системы.
 *
 * Раньше <title> приходил из APP_NAME один раз, и у всех страниц было
 * одинаковое название: во вкладках, в истории и в поиске это
 * выглядело как «один и тот же сайт». Хук живёт здесь, а не в
 * Router/index.js, потому что плагин i18n создаётся в этом файле, а
 * импорт app.js из роутера дал бы циклическую зависимость.
 *
 * Берётся КЛЮЧ раздела (meta.titleKey), а не готовая строка: язык
 * переключается на лету, и собранный заголовок остался бы на старом.
 */
const applyTitle = () => {
  const page = current.meta?.titleKey ? i18n.global.t(`app.pages.${current.meta.titleKey}`) : ''
  const name = i18n.global.t('app.title')

  // Подстановка именованная: в vue-i18n это {page}, а %(page)s —
  // синтаксис PHP/gettext, из-за чего заголовок выводился шаблоном.
  document.title = page ? i18n.global.t('app.titleBy', { page }) : name
}

// Текущий маршрут храним сами: хук afterEach не срабатывает при смене
// языка, а заголовок обязан меняться вместе с интерфейсом.
let current = { meta: {} }

router.afterEach((to) => {
  current = to
  applyTitle()
})

watch(i18n.global.locale, applyTitle)

// Тема. Атрибут на <html> уже выставлен инлайновым скриптом в
// app.blade.php (чтобы не мигать), здесь синхронизируется сам Vuetify
// и подписывается слушатель системной настройки для режима «как в
// системе».
theme.init(vuetify);

app.mount('#app');
