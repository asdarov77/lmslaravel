//import { createRouter,createWebHistory } from 'vue-router';
import { createRouter,createWebHashHistory } from 'vue-router';
import routes from './routes';
import { TokenService } from '../services/storage.service';
import store from '../Store';

const router = createRouter({
//    history: createWebHistory(),
    history: createWebHashHistory(),
    routes
})

// Защита маршрутов: авторизация + права (meta.permission).
// Фронтенд-проверки — только UX-фильтр; реальную безопасность
// обеспечивает бэкенд (middleware `permission:` / Gate).

// Флаг «права уже синхронизированы с сервером» для текущей жизни
// приложения (перезагрузка страницы сбрасывает его намеренно).
let permissionsSynced = false

router.beforeEach(async (to , from, next) => {
        // Раньше здесь стояло localStorage.getItem("token") напрямую:
        // при недоступном хранилище (about:blank, sandbox, приватный
        // режим) исключение всплывало как pageerror. TokenService
        // возвращает null вместо throw.
        const token = TokenService.getToken();
        if(!token) {
            if(to.name === 'login' || to.name === 'regist') // если не авторизован,то открываем доступ для регистрации и авторизации
            {
                return next()
            }
            else
            {
                return next({ name : 'login' })
            }
        }

        // Публичные страницы ошибок доступны всем авторизованным
        if (to.name === '403' || to.name === '404' || to.name === '500') {
            return next()
        }

        // Требование прав объявлено в meta защищённых маршрутов
        // (стиль Laravel Gate): достаточно ЛЮБОГО из списка (OR),
        // как в middleware `permission:a,b` на бэкенде.
        const required = to.meta?.permission

        // RBAC-синхронизация: при первом переходе после перезагрузки
        // страницы права во фронт-сторе приходят из LocalStorage — это
        // снимок момента логина. Если администратору/инструктору выдали
        // новые права, а сессия (токен) живёт дольше, чем перелогин,
        // меню остаётся «укороченным», пока не дернем GET /api/v1/me.
        // Флаг синхронизации — на сессию жизни приложения (не в LS).
        if (!permissionsSynced && TokenService.getToken()) {
            permissionsSynced = true
            await store.dispatch('Auth/fetchCurrentUser').catch(() => null)
        }

        if (Array.isArray(required) && required.length > 0) {
            // Синхронизируем права с сервером (source of truth), но не
            // блокируем навигацию при сетевых сбоях — fetchCurrentUser
            // сам логирует ошибку и возвращает null.
            await store.dispatch('Auth/fetchCurrentUser').catch(() => null)

            const can = store.getters['Auth/hasPermission']
            if (!can(required)) {
                return next({ name: '403' })
            }
        }

        next()
})


export default router