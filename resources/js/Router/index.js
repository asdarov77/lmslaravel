//import { createRouter,createWebHistory } from 'vue-router';
import { createRouter,createWebHashHistory } from 'vue-router';
import routes from './routes';
import { TokenService } from '../services/storage.service';

const router = createRouter({
//    history: createWebHistory(),
    history: createWebHashHistory(),
    routes
})

// защита от неавторизованных

router.beforeEach((to , from, next) => {
        // Раньше здесь стояло localStorage.getItem("token") напрямую:
        // при недоступном хранилище (about:blank, sandbox, приватный
        // режим) исключение всплывало как pageerror. TokenService
        // возвращает null вместо throw.
        const token = TokenService.getToken();
        //console.log(to.name , 'куда');
        //console.log(from.name, 'откуда');
        //console.log(token);
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
         next()
})


export default router