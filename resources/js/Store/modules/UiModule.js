import { LanguageService } from '../../services/language.service'
import density from '../../utils/density'

const UiModule = {
    namespaced: true,
    state: () => ({

        menudrawler: false,
        language: LanguageService.getLanguage(),
        // Плотность интерфейса: default или compact. Начальное значение
        // берётся из сохранённого выбора — иначе после перезагрузки
        // переключатель показывал бы «обычную», хотя страница компактная.
        density: density.read(),
        // errors: {}
    }),
    mutations: {

        LOGIN_SUCCESS(state) {

            state.menudrawler = true
        },
        //   LOGIN_ERROR(state, errors) {
        //     state.errors = errors
        //   },
        LOGOUT_SUCCESS(state) {

            // const newState = initialState()    
            // Object.keys(newState).forEach(key => {
            //   state[key] = newState[key]
            // })
            state.menudrawler = false

        },
        //   SET_USER(state, user) {
        //     state.user = user
        //   },
        SET_LANGUAGE(state, lang) {
            state.language = lang
        },
        SET_DENSITY(state, density) {
            state.density = density
        },
        //   // eslint-disable-next-line no-unused-vars
        //  RESET(state) {}

    },
    actions: {

        login({ commit }) {
            commit('LOGIN_SUCCESS')
        },

        logout({ commit }) {
            commit('LOGOUT_SUCCESS')
        }

    },
    getters: {
        // menudrawler: state => {    
        //   return false
        // },
    }
}

export default UiModule;