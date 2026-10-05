/**
 * Канонические роли пользователя — единая точка нормализации.
 *
 * Зеркало User::ROLE_ALIASES на бэкенде. Приложение исторически пишет
 * роль по-разному: в UI — «Обучаемый», в API-тестах и сид-данных —
 * «trainee». Сравнения строк в компонентах расходились с бэкендовыми
 * проверками, поэтому роль приводится к каноническому slug'у здесь.
 */

/** @type {Record<string, string[]>} */
export const ROLE_ALIASES = {
    admin: ["admin", "Администратор"],
    instructor: ["instructor", "Инструктор"],
    trainee: ["trainee", "Обучаемый"],
};

/**
 * Любое написание роли -> канонический slug.
 * Неизвестное значение возвращается как есть: UI должен показать его,
 * а не молча получить пустое состояние.
 *
 * @param {unknown} value
 * @returns {string|null}
 */
export const canonicalRoleSlug = (value) => {
    const raw = String(value ?? "").trim();
    if (raw === "") return null;

    for (const [canonical, variants] of Object.entries(ROLE_ALIASES)) {
        if (variants.some((variant) => variant.toLowerCase() === raw.toLowerCase())) {
            return canonical;
        }
    }

    return raw;
};

/**
 * Роли пользователя из любого источника: отдельный массив ролей
 * ([{slug}] или строки), поле role_slugs и строковая колонка user.role.
 *
 * Объединение нужно потому, что роль, назначенная через chroll,
 * существует только в role_user и в user.role не отражается.
 *
 * @param {object|null} user
 * @param {Array} [roles]
 * @returns {string[]}
 */
export const roleSlugsFrom = (user, roles) => {
    const set = new Set();
    const push = (value) => {
        const slug = canonicalRoleSlug(value);
        if (slug) set.add(slug);
    };

    if (Array.isArray(roles)) {
        roles.forEach((role) => push(typeof role === "string" ? role : role?.slug || role?.name));
    }

    if (Array.isArray(user?.role_slugs)) {
        user.role_slugs.forEach(push);
    }

    push(user?.role);

    return [...set];
};
