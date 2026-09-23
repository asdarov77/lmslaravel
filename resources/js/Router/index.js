//import { createRouter,createWebHistory } from 'vue-router';
import { createRouter,createWebHashHistory } from 'vue-router';
import routes from './routes';

const router = createRouter({
//    history: createWebHistory(),
    history: createWebHashHistory(),
    routes
})

// защита от неавторизованных

router.beforeEach((to , from, next) => {
        // const token = localStorage.getItem("token", token)   
        const token = localStorage.getItem("token");   
        //console.log(to.name , 'куда');
        //console.log(from.name, 'откуда');
        //console.log(token);
        if(!token || token === 'undefined') {                                    
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