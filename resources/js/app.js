
import './bootstrap.js';
//import '../css/app.css';

import 'vuetify/styles';
import { createApp } from 'vue';

//import { createVuetify } from 'vuetify'
// import * as Vuex from 'vuex';
// import * as VueRouter from 'vue-router';
import Vuelidate from 'vuelidate'

import router from './Router';
import store from './Store';
// import store  from './Store/test.js';
import vuetify from './Vuetify';



import App from './App.vue';
import { languages } from './locales';
import { defaultLocale } from './locales';
import { createI18n, useI18n } from 'vue-i18n';



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
  .mount('#app');
