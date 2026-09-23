import { TokenService } from "../../services/storage.service";
import { UserService } from "../../services/user.service";
import { login } from "../../api/auth.api";

// Безопасное получение пользователя из LocalStorage
const getInitialUser = () => {
    try {
        const userStr = UserService.getUser();
        return userStr ? JSON.parse(userStr) : {};
    } catch (e) {
        console.error("Ошибка парсинга user из LocalStorage", e);
        return {};
    }
};

const AuthModule = {
    namespaced: true,
    state: () => ({
        accessToken: TokenService.getToken() || null,
        user: getInitialUser(),
        // КРИТИЧНО: Все поля должны быть объявлены здесь для реактивности
        errors: null,
        language: "ru", // или null, в зависимости от дефолта
    }),

    mutations: {
        LOGIN_SUCCESS(state, accessToken) {
            state.accessToken = accessToken;
            state.errors = null; // Очищаем ошибки при успешном входе
        },
        LOGIN_ERROR(state, errors) {
            state.errors = errors;
        },
        LOGOUT_SUCCESS(state) {
            // Очищаем стейт, чтобы не хранить данные в памяти после логаута
            state.accessToken = null;
            state.user = {};
            state.errors = null;
        },
        SET_USER(state, user) {
            state.user = user;
        },
        SET_LANGUAGE(state, lang) {
            state.language = lang;
        },
    },

    actions: {
        async login({ commit }, formData) {
            try {
                const response = await login(formData);

                TokenService.saveToken(response.data.token);
                UserService.saveUser(JSON.stringify(response.data.user));

                commit("LOGIN_SUCCESS", response.data.token);
                commit("SET_USER", response.data.user);

                return response;
            } catch (error) {
                // Безопасное извлечение ошибок (защита от ошибок сети/CORS)
                const serverErrors =
                    error.response?.data?.errors ||
                    error.message ||
                    "Произошла ошибка сети";

                commit("LOGIN_ERROR", serverErrors);
                return Promise.reject(error);
            }
        },

        logout({ commit }) {
            UserService.removeUser();
            TokenService.removeToken();
            commit("LOGOUT_SUCCESS"); // Теперь это реально очистит стейт
        },
    },

    getters: {
        loggedIn: (state) => {
            // console.log убран, чтобы не спамить в консоль
            return !!state.accessToken;
        },

        hasPermission: (state) => (permissions, contentType) => {
            // Защита от падения, если user или permissions отсутствуют
            if (!state.user || !Array.isArray(state.user.permissions))
                return false;

            // На случай, если в компонент передали одну строку вместо массива
            const requiredPermissions = Array.isArray(permissions)
                ? permissions
                : [permissions];

            // Оптимизация: используем один some вместо map().some()
            return requiredPermissions.some((perm) =>
                state.user.permissions.some(
                    (p) => p.name === contentType && p.slug === perm,
                ),
            );
        },
    },
};

export default AuthModule;
