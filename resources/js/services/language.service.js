const LANG_KEY = 'lang'

/**
 * Manage the how UI language is being stored and retrieved from storage.
 *
 * Current implementation stores to localStorage. Local Storage should always be
 * accessed through this instance.
 **/
const LanguageService = {
  getLanguage() {
    return localStorage.getItem(LANG_KEY)
  },
  saveLanguage(lang) {
    localStorage.setItem(LANG_KEY, lang)
  }
}

export { LanguageService }
