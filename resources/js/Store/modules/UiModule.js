import { LanguageService } from '../../services/language.service'

const UiModule = {
    namespaced: true,
    state: () => ({

        menudrawler: false,
        language: LanguageService.getLanguage(),
        // errors: {}
    }),
    mutations: {

        LOGIN_SUCCESS(state) {

            state.menudrawler = true
            //console.log('action login mutation',state)
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
            //console.log('action logiout mutation',state)

        },
        //   SET_USER(state, user) {
        //     state.user = user
        //   },
        SET_LANGUAGE(state, lang) {
            state.language = lang
        },
        //   // eslint-disable-next-line no-unused-vars
        //  RESET(state) {}

    },
    actions: {

        login({ commit }) {
            //  console.log('action login')
            commit('LOGIN_SUCCESS')
        },

        logout({ commit }) {
            //console.log('action logout', state)
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