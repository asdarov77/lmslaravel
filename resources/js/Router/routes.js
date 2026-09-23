import store from '../Store'

const hasPermission = store.getters['Auth/hasPermission']

const routes = [

    {
        path: '/',
        component: () => import('../Pages/Home.vue'),
        name: 'home'
    },
    {
        path: '/login',
        component: () => import('../Pages/Login.vue'),
        name: 'login',
    },
    {
        path: '/reg',
        component: () => import('../Pages/Register.vue'),
        name: 'regist'

    },
    {
        path: '/about',
        component: () => import('../Pages/About.vue'),
        name: 'about'

    },
    {
        path: '/contacts',
        component: () => import('../Pages/Contacts.vue'),
        name: 'contacts',
        beforeEnter(to, from, next) {
            if (hasPermission(['manage-users'], 'Manage users')) next()
            else next('/my')
        }
    },
    {
        path: '/my',
        component: () => import('../Pages/MyAccount.vue'),
        name: 'myaccount'
    },
    {
        path: '/logout',
        component: () => import('../Pages/Navigation/LogoutApp.vue'),
        name: 'logout'
    },
    //
    // Блок пользователей
    //
    {
        path: '/user/list',
        component: () => import('../Pages/UserList.vue'),
        name: 'user.list',
    },
    {
        path: '/user/edit/:idEdit',
        component: () => import('../Pages/User/UserItemEdit.vue'),
        name: 'user.edit',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        // beforeEnter(to, from, next) {            
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    {
        path: '/user/chpass/:idEdit',
        component: () => import('../Pages/User/UserChpass.vue'),
        name: 'user.chpass',
        props: true,
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    // {
    //     path: '/user/chroll',
    //     component: () => import('../Pages/User/UserChrole.vue'),
    //     name: 'user.chroll',
    //     //props: true
    // },
    // {
    //     path: '/user/chperm/:idEdit',
    //     component: () => import('../Pages/User/UserChperm.vue'),
    //     name: 'user.chperm',
    //     props: route => ({ idEdit: Number(route.params.idEdit)}),
    //     beforeEnter(to, from, next) {
    //         if (hasPermission(['manage-users'], 'Manage users')) next()
    //         else next('/403')
    //     }
    // },
    //
    // Блок групп
    //
    {
        path: '/groups/list',
        component: () => import('../Pages/GroupList.vue'),
        name: 'groups.index'
    },
    {
        path: '/groups/add',
        component: () => import('../Pages/Group/CreateGroup.vue'),
        name: 'groups.create',
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users','create-tasks'], 'User')) next()
        //     else next('/403')
        // }
    },
    {
        path: '/groups/edit/:idEdit',
        component: () => import('../Pages/Group/GroupItemEdit.vue'),
        name: 'groups.update',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    // {
    //     path: '/groups',
    //     component: () => import('../Pages/Group/RegisterGroup.vue'),
    //     name: 'groups.store'
    // },
    //
    // Блок курсов
    //
    // старый контроллер
    {
        path: '/courses/list',
        component: () => import('../Pages/Courses.vue'),
        name: 'courses.list',
        //props: true // разрешение на передачу данных через router.parms
    },
    {
        // тестовый вариант открытия страницы(рабочий)
        path: '/courses/item/:idEdit',
        component: () => import('../Pages/CourseTest.vue'),
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        name: 'courses.item',
        //props: true // разрешение на передачу данных через router.parms
    },
    {
        // тестовый вариант открытия страницы(манифест)
        path: '/courses/itemmani',
        component: () => import('../Pages/CourseManifest.vue'),
        // props: route => ({ idEdit: Number(route.params.idEdit)}),
        props: (route) => ({
            idEdit: parseInt(route.query.idEdit),
            idCategory: parseInt(route.query.idCategory),
        }),
        name: 'courses.itemmani',

    },
    {
        path: '/courses/desc/:idEdit',
        component: () => import('../Pages/CourseItem.vue'),
        name: 'courses.desc',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        beforeEnter(to, from, next) {
            if (hasPermission(['manage-users'], 'Manage users')) next()
            else next('/403')
        }
    },
    // новый (ресурсный) контроллер
    {
        path: '/course',
        component: () => import('../Pages/Course/RegisterCourse.vue'),
        name: 'course.store',
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    {
        path: '/course/:idEdit',
        component: () => import('../Pages/Course/UpdateCourse.vue'),
        name: 'course.update',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    {
        path: '/classes',
        component: () => import('../Pages/Course/AddClass.vue'),
        name: 'air.store',

    },
    //
    // Блок категорий
    //
    // новый (ресурсный) контроллер категорий курсов
    {
        path: '/categories',
        component: () => import('../Pages/Category/CategoryList.vue'),
        name: 'categories.index',
        // beforeEnter(to, from, next) {
        //     if (hasPermission(['manage-users'], 'Manage users')) next()
        //     else next('/403')
        // }
    },
    {
        path: '/register-categories',
        component: () => import('../Pages/Category/RegisterCategory.vue'),
        name: 'categories.store',
    },
    {
        path: '/categories/:idEdit',
        component: () => import('../Pages/Category/UpdateCategory.vue'),
        name: 'categories.update',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        //props: true
    },
    //
    // Блок файлов
    //
    {
        path: '/files/add',
        component: () => import('../Pages/FileLoadSimple.vue'),
        name: 'files.simple',
    },
    {
        path: '/calendar',
        component: () => import('../Pages/EventCalendar.vue'),
        name: 'calendar',
    },
    {
        path: '/group/learning/:idEdit',
        component: () => import('../Pages/Group/GroupLearning.vue'),
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        name: 'group.learning',
    },
    {
        path: '/auk',
        component: () => import('../Pages/User/UserPage.vue'),
        name: 'index',
    },
    //--------------------------- блок вопросов-----------------------------------
    {
        path: '/upload-gift',
        component: () => import('../Pages/Gift/GiftImportForm.vue'),
        name: 'upload-gift',
    },
    {
        path: '/questions',
        component: () => import('../Pages/Gift/ExamineItem.vue'),
        props: (route) => ({
            idEdit: parseInt(route.query.idEdit),
            idCategory: parseInt(route.query.idCategory),
        }),
        name: 'questions',
    },
    {
        path: '/questions-main',
        component: () => import('../Pages/Gift/ExamineMain.vue'),
        props: (route) => ({
            idEdit: parseInt(route.query.idEdit),
            idCategory: parseInt(route.query.idCategory),
        }),
        name: 'questions.main',
    },
    {
        path: '/questions-main/:idEdit',
        component: () => import('../Pages/Gift/QuestionEdit.vue'),
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        name: 'question.edit',
    },
    // {
    //     path: '/questions-main/:idEdit?',
    //     component: () => import('../Pages/Gift/QuestionEdit.vue'),
    //     props: route => ({ idEdit: route.params.idEdit ? Number(route.params.idEdit) : null }),
    //     name: 'question.edit'
    // },
    {
        //        path: '/questions/:idEdit',
        path: '/questions',
        component: () => import('../Pages/Gift/QuestionItem.vue'),
        // props: route => ({ idEdit: Number(route.params.idEdit) }),
        props: (route) => ({
            idEdit: parseInt(route.query.idEdit),
            idCategory: parseInt(route.query.idCategory),
        }),
        name: 'question.item',
    },


    // {
    //     // тестовый вариант открытия страницы(манифест)
    //     path: '/courses/itemmani',
    //     component: () => import('../Pages/CourseManifest.vue'),
    //     // props: route => ({ idEdit: Number(route.params.idEdit)}),
    //     props: (route) => ({
    //         idEdit: parseInt(route.query.idEdit),
    //         idCategory: parseInt(route.query.idCategory),
    //     }),
    //     name: 'courses.itemmani',

    // },

    {
        //path: '/questions-main/new',
        path: '/questions-main/new/:category_id/:aukstructure_id',
        component: () => import('../Pages/Gift/QuestionNew.vue'),
        //props: route => ({ category_id: Number(route.params.category_id), aukstructure_id: Number(route.params.aukstructure_id) }),
        // props: (route) => ({
        //     category_id: parseInt(route.query.current_category),
        //     aukstructure_id: parseInt(route.query.current_auk_theme),
        // }),
        props: (route) => ({
            category_id: Number(route.params.category_id),
            aukstructure_id: Number(route.params.aukstructure_id)
        }),
        name: 'question.new',
    },
    //--------------------------- конец блок вопросов-----------------------------------

    // удалить

    // {
    //     path: '/auk/',
    //     component: () => import('../Pages/Test/index.vue'),
    //     name: 'index',
    // },
    // {
    //     path: '/auk/:auk',
    //     component: () => import('../Pages/Test/courses.show.vue'),
    //     props: route => ({ nameLyx: String(route.params.auk) }),
    //     name: 'auk.show',
    // },
    // {
    //     path: '/auk/:course/lern/:title',
    //     component: () => import('../Pages/Test/courses.lern.vue'),
    //     props: route => ({ course: String(route.params.course), title: String(route.params.title), }),
    //     name: 'auk.lern',
    // },
    //--------------------- конец удалить-------------
    // пробный файловый менеджер
    {
        path: '/filemanager',
        component: () => import('../Pages/Filemanager/FileManager.vue'),
        //props: route => ({ nameLyx: String(route.params.filemanager) }),
        name: 'filemanager',
    },
    {
        path: '/datepicker',
        component: () => import('../Pages/Calendar/FormPicker.vue'),
        //props: route => ({ nameLyx: String(route.params.filemanager) }),
        name: 'filemanager5',
    },
    // {
    //     path: '/filemanager2/',
    //     component: () => import('../Pages/Filemanager/FileManager2.vue'),
    //     //props: route => ({ nameLyx: String(route.params.filemanager) }),
    //     name: 'filemanager2',        
    // },
    // {
    //     path: '/filemanager/:auk',
    //     component: () => import('../Pages/Filemanager/FileManagerList.vue'),
    //     props: route => ({ nameLyx: Object(route.params.auk) }),
    //     name: 'filemanager.list',
    // },




    // {
    //     path: '/auk/:auk',
    //     component: () => import('../Pages/Test/courses.show.vue'),
    //     props: route => ({ nameLyx: String(route.params.auk) }),
    //     name: 'auk.show',
    // },
    // {
    //     path: '/test/index',
    //     component: () => import('../Pages/Test/courses.lern.chapter.vue'),
    //     name: 'courses.lern.chapter',
    // },
    // {
    //     //path: '/test/index',
    //     component: () => import('../Pages/Test/courses.lern.chapter.section.vue'),
    //     name: 'courses.lern.chapter.section',
    // },
    // {
    //     //path: '/test/index',
    //     component: () => import('../Pages/Test/courses.lern.chapter.section.subsection.vue'),
    //     name: 'courses.lern.chapter.section.subsection',
    // },
    {
        path: '/user-course/:id',
        name: 'userCourse',
        component: () => import('../Pages/User/UserCourse.vue'),
        props: route => ({ id: Number(route.params.id) }),
    },
    // блок настроек
    {
        path: '/grade-boundary/',
        name: 'gradeBoundary',
        component: () => import('../Pages/Settings/GradeSettings.vue'),
    },
    {
        path: '/settings/',
        name: 'allSettings',
        component: () => import('../Pages/Settings/AllSettings.vue'),
    },
    //
    {
        path: '/403',
        name: '403',
        component: () => import('../Pages/Errors/_403.vue'),

    },
    {
        path: '/404',
        name: '404',
        component: () => import('../Pages/Errors/_404.vue'),

    },
    {
        path: '/500',
        name: '500',
        component: () => import('../Pages/Errors/_500.vue')
    },
    // {
    //     path: '/api/private/*',
    //     name: 'redirected',
    //     component: () => import('../Pages/Redirected.vue')
    // },    
    // {
    //     path: '*',
    //     redirect: '404'
    //   },

]

export default routes;


