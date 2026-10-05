import { TokenService } from "../../services/storage.service";
import { UserService } from "../../services/user.service";
import { login, logout, fetchMe } from "../../api/auth.api";
import { canonicalRoleSlug, ROLE_ALIASES, roleSlugsFrom } from "../../utils/roles";

const AuthService = { login, logout, fetchMe };

// Legacy-алиасы каталога прав (зеркало config/permissions.php на бэкенде).
// Позволяют фронту понимать и старые slug'и (manage-users), и новые
// (users.view) без перелогина после миграции данных.
const PERMISSION_ALIASES = {
    "manage-users": ["users.view"],
    "users.view": ["manage-users"],
    "create-tasks": ["users.courses"],
    "users.courses": ["create-tasks"],
    "manage-course": ["courses.manage"],
    "edit_courses": ["courses.manage"],
    "courses.manage": ["manage-course", "edit_courses"],
};

const expandPermission = (name) => {
    const set = new Set([name]);
    for (const alias of PERMISSION_ALIASES[name] || []) set.add(alias);
    return set;
};

// Нормализация прав пользователя: массив объектов {slug,name} или строк.
const permissionNames = (user) => {
    const list = Array.isArray(user?.permissions) ? user.permissions : [];
    return list
        .map((p) => (typeof p === "string" ? p : p?.slug || p?.name))
        .filter(Boolean);
};

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
        // Эффективный набор прав (slug'и) — единственный источник истины
        // для hasPermission/can. Раньше он выводился из user.permissions,
        // где лежали только строки таблицы permissions. Пока каталог не
        // синхронизирован (permissions:sync), у администратора без явных
        // записей набор был пустым, и меню фильтровалось до нуля пунктов.
        // Первичное значение — снимок из LocalStorage; роутер при первом
        // переходе дёргает GET /api/v1/me и заменяет его актуальным.
        permissionSlugs: permissionNames(getInitialUser()),
        // Канонические slug'ы ролей. Отдельное поле, а не вычисление из
        // user.role: роль из role_user (назначается через chroll) в
        // строковой колонке отсутствует, и сверка только по ней считала
        // такого пользователя «без роли» — Home.vue показывал пустой экран.
        roleSlugs: roleSlugsFrom(getInitialUser(), getInitialUser()?.roles),
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
            state.permissionSlugs = [];
            state.roleSlugs = [];
            state.errors = null;
        },
        SET_USER(state, user) {
            state.user = user;
        },
        SET_ROLE_SLUGS(state, slugs) {
            state.roleSlugs = Array.isArray(slugs)
                ? [...new Set(slugs.map(canonicalRoleSlug).filter(Boolean))]
                : [];
        },
        SET_PERMISSIONS(state, slugs) {
            state.permissionSlugs = Array.isArray(slugs)
                ? slugs.filter(Boolean).map(String)
                : [];
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
                // login отдаёт строки таблицы permissions; полный
                // эффективный набор приходит из GET /api/v1/me, который
                // вызывает роутер при первом переходе.
                commit("SET_PERMISSIONS", permissionNames({ permissions: payload.permissions }));
                commit("SET_ROLE_SLUGS", roleSlugsFrom(user, payload.roles));

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

        /**
         * Синхронизация профиля и прав с сервером (GET /api/v1/me).
         * Вызывается при старте приложения и после chperm, чтобы state
         * не расходился с БД (бэкенд — источник истины RBAC).
         */
        async fetchCurrentUser({ commit }) {
            try {
                const response = await AuthService.fetchMe();
                const payload = response.data?.data ?? response.data;
                if (!payload?.user) return null;

                const user = payload.user;
                user.permissions = Array.isArray(payload.permissions)
                    ? payload.permissions
                    : permissionNames(user);

                // Приоритет у permission_slugs: это ЭФФЕКТИВНЫЙ набор прав
                // (прямые + через роли + legacy-алиасы + полный каталог для
                // суперадмина), собранный на бэкенде — источнике истины.
                // Строки payload.permissions берём лишь как запасной вариант
                // на случай ответа без permission_slugs.
                const effectiveSlugs = Array.isArray(payload.permission_slugs)
                    ? payload.permission_slugs
                    : permissionNames({ permissions: payload.permissions });
                if (effectiveSlugs.length > 0) {
                    commit("SET_PERMISSIONS", effectiveSlugs);
                }
                if (typeof payload.is_super_admin === "boolean") {
                    user.is_super_admin = payload.is_super_admin;
                }

                // Роли — из role_slugs (уже канонические) либо из массива
                // roles. Приоритет у role_slugs по той же причине, что и у
                // permission_slugs: это эффективное значение с бэкенда.
                const effectiveRoles = Array.isArray(payload.role_slugs) && payload.role_slugs.length > 0
                    ? payload.role_slugs
                    : roleSlugsFrom(user, payload.roles);
                if (effectiveRoles.length > 0) {
                    user.role_slugs = effectiveRoles;
                    commit("SET_ROLE_SLUGS", effectiveRoles);
                }

                UserService.saveUser(user);
                commit("SET_USER", user);
                return user;
            } catch (error) {
                // 401 обработает интерцептор httpClient (logout+redirect);
                // здесь только сетевые сбои — не роняем приложение.
                console.error("fetchCurrentUser:", error?.message);
                return null;
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
        loggedIn: (state) => !!state.accessToken,

        /**
         * Канонические slug'ы ролей: state.roleSlugs (наполняется из
         * login и GET /api/v1/me) с дозагрузкой из state.user, если
         * стейт собран из старого снимка в LocalStorage.
         */
        roleSlugs: (state) => {
            const merged = new Set(state.roleSlugs || []);
            for (const slug of roleSlugsFrom(state.user, state.user?.roles)) merged.add(slug);
            return [...merged];
        },

        /**
         * hasRole('admin') — единая проверка роли вместо сравнений
         * вида user.role === 'Обучаемый' в компонентах.
         */
        hasRole: (state, getters) => (...slugs) => {
            const required = slugs.flat().filter(Boolean).map((s) => canonicalRoleSlug(s));
            if (required.length === 0) return true;
            return required.some((slug) => getters.roleSlugs.includes(slug));
        },

        isAdmin: (state, getters) => getters.hasRole("admin"),
        isInstructor: (state, getters) => getters.hasRole("instructor"),
        isTrainee: (state, getters) => getters.hasRole("trainee"),

        /**
         * Супер-администратор: роль admin/Администратор ИЛИ флаг от
         * бэкенда (GET /api/v1/me -> is_super_admin). Дублирует
         * User::isSuperAdmin() и Gate::before на бэкенде.
         *
         * Роли разворачиваются здесь, а не через геттер hasRole:
         * от этого геттера зависят hasPermission и can, и их нельзя
         * вызывать с частично собранным набором геттеров (так делают
         * и юнит-тесты) — иначе проверка падает с
         * «getters.hasRole is not a function».
         */
        isSuperAdmin: (state) =>
            !!state.user?.is_super_admin ||
            roleSlugsFrom(state.user, state.user?.roles).includes("admin"),

        /**
         * Множество прав пользователя (slug + алиасы каталога).
         * Источник — state.permissionSlugs, который наполняется
         * эффективным набором из GET /api/v1/me.
         */
        permissionSet: (state) => {
            const set = new Set();
            for (const name of state.permissionSlugs) {
                for (const expanded of expandPermission(name)) set.add(expanded);
            }
            return set;
        },

        /**
         * hasPermission(perm, [perm...]) — единый стиль проверки:
         * принимает строку или массив, достаточно ЛЮБОГО совпадения (OR),
         * как на бэкенде (middleware `permission:a,b`).
         *
         * Совместимо со старыми вызовами hasPermission(['manage-users'], 'Manage users')
         * — второй аргумент (contentType) игнорируется: сверка идёт по slug/name
         * из первого аргумента, что устраняет расхождение двух стилей вызова.
         */
        hasPermission: (state, getters) => (perm, contentType) => {
            const required = (Array.isArray(perm) ? perm : [perm])
                .filter(Boolean)
                .map(String);
            if (required.length === 0) return true; // пункт без требований доступен всем

            // Супер-администратор — все права (как Gate::before на бэкенде).
            if (getters.isSuperAdmin) return true;

            const held = getters.permissionSet;
            if (held.size === 0) return false;

            return required.some((r) => held.has(r));
        },

        /**
         * can('users.view') — рекомендуемый новый API проверок (стиль
         * Laravel Gate / v-can). То же, что hasPermission, но короче.
         * Если передан массив — все права обязательны (AND), как
         * middleware `permission:a,b` в CheckAllPermissions.
         */
        can: (state, getters) => (...perms) => {
            const required = perms.flat().filter(Boolean).map(String);
            if (required.length === 0) return true;
            if (getters.isSuperAdmin) return true;
            if (Array.isArray(perms[0])) {
                return required.every((r) => getters.permissionSet.has(r));
            }
            return required.some((r) => getters.permissionSet.has(r));
        },
    },
};

export { canonicalRoleSlug, ROLE_ALIASES };
export default AuthModule;
