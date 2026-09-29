import { TokenService } from "../../services/storage.service";
import { UserService } from "../../services/user.service";
import { login, logout } from "../../api/auth.api";

const AuthService = { login, logout };

// Безопасное получение пользователя из LocalStorage
const getInitialUser = () => {
    try {
        const user = UserService.getUser();
        return user || {};
    } catch (e) {
        console.error("Ошибка получения user из LocalStorage", e);
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

                // API оборачивает ответы в envelope {success, data, error, meta}
                const payload = response.data?.data ?? response.data;

                // Бэкенд возвращает permissions отдельным полем рядом с user
                // (см. AuthController::login), а не внутри объекта user.
                // Склеиваем их, иначе state.user.permissions всегда пустой
                // и боковое меню фильтруется до нуля пунктов.
                const user = payload.user;
                if (user && !Array.isArray(user.permissions)) {
                    user.permissions = Array.isArray(payload.permissions)
                        ? payload.permissions
                        : [];
                }

                TokenService.saveToken(payload.token);
                UserService.saveUser(user);

                commit("LOGIN_SUCCESS", payload.token);
                commit("SET_USER", user);

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
            // Сначала инвалидируем токен на сервере, иначе он остаётся
            // рабочим после выхода из приложения. Локальное состояние
            // чистим в любом случае: ошибка сети не должна мешать выйти.
            const done = () => {
                UserService.removeUser();
                TokenService.removeToken();
                commit("LOGOUT_SUCCESS");
            };
            return AuthService.logout().then(done).catch(done);
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

            // Вызов вида hasPermission(['manage-users'], 'Manage users')
            // означает «есть право с slug ИЛИ name из списка» — так
            // совместимы оба стиля вызова в компонентах и роутере.
            // Строгая пара (name === contentType && slug === perm) раньше
            // никогда не срабатывала и скрывала пункты меню и целые
            // страницы даже у администраторов.
            const matches = requiredPermissions.some((perm) =>
                state.user.permissions.some(
                    (p) => p.slug === perm || p.name === perm,
                ),
            );
            if (matches) return true;

            // Второй стиль: contentType как имя права + operations как
            // список действий (например 'User' + ['read','write']).
            return state.user.permissions.some(
                (p) =>
                    p.name === contentType &&
                    requiredPermissions.some(
                        (op) =>
                            op === "all" ||
                            (Array.isArray(p.operations) &&
                                p.operations.includes(op)),
                    ),
            );
        },
    },
};

export default AuthModule;
