<template>
  <v-select
    v-model="lang"
    :items="items"
    item-text="lang"
    item-value="abbr"
    label="Язык"
    prepend-icon="mdi-web"
    return-object
    single-line
    @change="setLocale"
  >
    <template v-slot:selection="data">
      <template v-if="data.item.abbr === 'en'">
        <EnFlag class="logo" />
      </template>
      <template v-else>
        <RuFlag class="logo" />
      </template>
      <span>
        {{ data.item.lang }}
      </span>
    </template>
    <template v-slot:item="{ item }">
      <v-list-item-icon>
        <template v-if="item.abbr === 'en'">
          <EnFlag class="logo" />
        </template>
        <template v-else>
          <RuFlag class="logo" />
        </template>
      </v-list-item-icon>
      <v-list-item-content>
        <v-list-item-title v-text="item.lang"></v-list-item-title>
      </v-list-item-content>
    </template>
  </v-select>
</template>

<script>
import EnFlag from '../locales/icons/flag_en.svg'
import RuFlag from '../locales/icons/flag_ru.svg'

const RU = { lang: 'Русский', abbr: 'ru' }
const EN = { lang: 'English', abbr: 'en' }
const initialLanguage = abbr => {
  if (abbr === 'ru') return RU
  else if (abbr === 'en') return EN
  else return EN
}

export default {
  name: 'LanguageSelector',
  components: {
    EnFlag,
    RuFlag
  },
  data() {
    return {
      lang: initialLanguage(this.$vuetify.lang.current),
      items: [RU, EN]
    }
  },
  methods: {
    setLocale({ abbr }) {
      this.$vuetify.lang.current = abbr
      this.$i18n.locale = abbr
    }
  }
}
</script>

<style scoped>
.logo {
  width: 28px;
  height: 28px;
  margin-right: 10px;
}
</style>
