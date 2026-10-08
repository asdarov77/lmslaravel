<template>
  <v-select
    v-model="lang"
    :items="items"
    item-title="lang"
    item-value="abbr"
    :label="$t('common.language')"
    prepend-inner-icon="mdi-web"
    return-object
    single-line
    density="compact"
    variant="outlined"
    hide-details
    class="lang-select"
    @change="setLocale"
  >
    <!--
      Значки языка — текстовые, а не картинки.

      Раньше здесь импортировались flag_ru.svg / flag_en.svg и
      использовались как компоненты (<RuFlag/>). При обычном импорте SVG
      Vite отдаёт СТРОКУ-URL, и Vue пытался создать элемент с именем
      «http://…/flag_ru.svg» — падало с InvalidCharacterError. Компонент
      был сломан и просто не подключался: в футере стояли свои кнопки.
    -->
    <template v-slot:selection="{ item }">
      <span class="lang-badge" :data-lang="item?.abbr">{{ item?.abbr?.toUpperCase() }}</span>
      <span>{{ item?.lang }}</span>
    </template>
    <template v-slot:item="{ item }">
      <!-- Vuetify 3: v-list-item-icon переименован в v-list-item-media. -->
      <v-list-item-media>
        <span class="lang-badge" :data-lang="item.abbr">{{ item.abbr.toUpperCase() }}</span>
      </v-list-item-media>
      <v-list-item-title v-text="item.lang"></v-list-item-title>
    </template>
  </v-select>
</template>

<script>
const RU = { lang: 'Русский', abbr: 'ru' }
const EN = { lang: 'English', abbr: 'en' }
const initialLanguage = abbr => {
  if (abbr === 'ru') return RU
  else if (abbr === 'en') return EN
  else return EN
}

export default {
  name: 'LanguageSelector',
  data() {
    return {
      /*
       * $vuetify читается опционально: компонент стоит и на странице
       * входа, и в футере корневого App.vue, и во втором случае
       * this.$vuetify на момент data() ещё не внедрён — обращение без
       * ?. роняло всё приложение («Cannot read properties of undefined»),
       * а не только переключатель языка.
       */
      lang: initialLanguage(this.$vuetify?.lang?.current),
      items: [RU, EN]
    }
  },
  methods: {
    setLocale({ abbr }) {
      if (this.$vuetify?.lang) {
        this.$vuetify.lang.current = abbr
      }

      if (this.$i18n) {
        this.$i18n.locale = abbr
      }

      this.$store?.commit("Ui/SET_LANGUAGE", abbr);
    }
  }
}
</script>

<style scoped>
/* Значок языка: цвет берётся из токенов темы, а не из картинки. */
.lang-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 26px;
  height: 20px;
  margin-right: 8px;
  border-radius: 4px;
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  background: var(--c-primary-soft);
  color: var(--c-primary);
}

.lang-select {
  max-width: 180px;
}
</style>
