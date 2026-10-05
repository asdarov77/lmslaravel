// RBAC: вместо снимка геттера на момент импорта модуля (права устаревали
// после логина) маршруты декларируют требования в meta.permission,
// а проверку выполняет глобальный guard в Router/index.js через store.

/**
 * Безопасное приведение query-параметра к числу.
 *
 * Раньше здесь стоял parseInt(route.query.idEdit), и при отсутствующем
 * параметре он давал NaN. Значение уходило в запрос как
 * /api/questions?aukstructure_id=NaN, а бэкенд корректно отвечал 422 —
 * страница «Вопросы» падала с ошибкой в консоли при обычном переходе.
 * Теперь нечисловой/отсутствующий параметр становится null, а страницы
 * не отправляют такой фильтр вовсе.
 */
const toIntOrNull = value => {
    if (value === undefined || value === null || value === '') return null;
    const n = Number.parseInt(String(value), 10);
    return Number.isFinite(n) ? n : null;
};

const routes = [

    {
        path: '/',
        meta: { titleKey: 'home' },
        component: () => import('../Pages/Home.vue'),
        name: 'home'
    },
    {
        path: '/login',
        meta: { titleKey: 'login' },
        component: () => import('../Pages/Login.vue'),
        name: 'login',
    },
    {
        path: '/reg',
        meta: { breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.reguser' }], titleKey: 'regist' },
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
        meta: { permission: ['users.view'] },    },
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
        meta: { permission: ['users.view'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.users' }], titleKey: 'users' },    },
    {
        path: '/user/edit/:idEdit',
        component: () => import('../Pages/User/UserItemEdit.vue'),
        name: 'user.edit',
        meta: { permission: ['users.update', 'users.view'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.users', to: '/user/list' }, { key: 'users.list.edit' }], titleKey: 'users' },        props: route => ({ idEdit: Number(route.params.idEdit) }),
    },
    {
        path: '/user/chpass/:idEdit',
        component: () => import('../Pages/User/UserChpass.vue'),
        name: 'user.chpass',
        meta: { permission: ['users.update'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.users', to: '/user/list' }, { key: 'users.list.password' }], titleKey: 'users' },        props: true,
    },
    // Назначение ролей. Маршрут был закомментирован вместе с API
    // (PUT /api/user/chroll/{id}), поэтому роль нельзя было назначить
    // ни через страницу, ни через API. Право — users.permissions, то же,
    // что у управления правами: назначение роли не мельче назначения
    // прав. chroll дополнительно запрещает менять собственные роли.
    {
        path: '/user/chrole/:idEdit',
        component: () => import('../Pages/User/UserChrole.vue'),
        name: 'user.chroll',
        meta: { permission: ['users.permissions'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.users', to: '/user/list' }, { key: 'users.chrole.title' }], titleKey: 'users' },
        props: route => ({ idEdit: Number(route.params.idEdit) }),
    },
    //
    // Управление правами — отдельная страница (было: колонка в списке
    // пользователей + диалог в карточке пользователя).
    // Доступна администратору (users.permissions) и инструктору
    // (users.view); что именно можно — решает PermissionScope на бэкенде.
    {
        path: '/permissions',
        component: () => import('../Pages/Permissions/PermissionsManager.vue'),
        name: 'permissions.manage',
        meta: { permission: ['users.permissions', 'users.view'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.permissions' }], titleKey: 'permissions' },
    },
    //
    // Блок групп
    //
    {
        path: '/groups/list',
        meta: { permission: ['groups.view', 'users.view'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.groups' }], titleKey: 'groups' },
        component: () => import('../Pages/GroupList.vue'),
        name: 'groups.index'
    },
    {
        path: '/groups/add',
        component: () => import('../Pages/Group/CreateGroup.vue'),
        name: 'groups.create',
        meta: { permission: ['groups.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.groups', to: '/groups/list' }, { key: 'groups.create.title' }], titleKey: 'groups' },    },
    {
        path: '/groups/edit/:idEdit',
        component: () => import('../Pages/Group/GroupItemEdit.vue'),
        name: 'groups.update',
        meta: { permission: ['groups.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.groups', to: '/groups/list' }, { key: 'groups.edit.title' }], titleKey: 'groups' },        props: route => ({ idEdit: Number(route.params.idEdit) }),
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
        meta: { permission: ['courses.view'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.courses' }], titleKey: 'courses' },
        component: () => import('../Pages/Courses.vue'),
        name: 'courses.list',
        //props: true // разрешение на передачу данных через router.parms
    },
    {
        // тестовый вариант открытия страницы(рабочий)
        path: '/courses/item/:idEdit',
        meta: { permission: ['courses.view', 'content.view'] },
        component: () => import('../Pages/CourseTest.vue'),
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        name: 'courses.item',
        //props: true // разрешение на передачу данных через router.parms
    },
    {
        // просмотр материалов курса.
        //
        // content.manage — только для методиста (загрузка контента),
        // обучаемому достаточно content.view. Раньше здесь стояли
        // courses.manage + content.manage, поэтому обучаемый с
        // courses.view и content.view получал 403 на своих же курсах.
        path: '/courses/itemmani',
        meta: { permission: ['content.view', 'content.manage'] },
        component: () => import('../Pages/CourseManifest.vue'),
        // props: route => ({ idEdit: Number(route.params.idEdit)}),
        props: (route) => ({
            idEdit: toIntOrNull(route.query.idEdit),
            idCategory: toIntOrNull(route.query.idCategory),
        }),
        name: 'courses.itemmani',

    },
    {
        path: '/courses/desc/:idEdit',
        component: () => import('../Pages/CourseItem.vue'),
        name: 'courses.desc',
        // Описание курса — это чтение, поэтому достаточно courses.view.
        // Право courses.manage нужно для правки, а не для просмотра.
        meta: { permission: ['courses.view', 'courses.manage'] },        props: route => ({ idEdit: Number(route.params.idEdit) }),
    },
    // новый (ресурсный) контроллер
    {
        path: '/course',
        component: () => import('../Pages/Course/RegisterCourse.vue'),
        name: 'course.store',
        meta: { permission: ['courses.manage'], titleKey: 'courses' },    },
    {
        path: '/course/:idEdit',
        component: () => import('../Pages/Course/UpdateCourse.vue'),
        name: 'course.update',
        meta: { permission: ['courses.manage'], titleKey: 'courses' },        props: route => ({ idEdit: Number(route.params.idEdit) }),
    },
    {
        path: '/classes',
        meta: { permission: ['content.manage'], titleKey: 'classes' },
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
        // Справочник специальностей — методическая страница.
        // courses.view тут был ошибкой: обучаемый им владеет, поэтому
        // OR-проверка пропускала его и позволяла открыть редактирование
        // чужих категорий, хотя бэкенд на запись всё равно отдаёт 403.
        meta: { permission: ['categories.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.categories' }], titleKey: 'categories' },    },
    {
        path: '/register-categories',
        component: () => import('../Pages/Category/RegisterCategory.vue'),
        name: 'categories.store',
        meta: { permission: ['categories.manage', 'courses.manage'], titleKey: 'categories' },    },
    {
        path: '/categories/:idEdit',
        component: () => import('../Pages/Category/UpdateCategory.vue'),
        name: 'categories.update',
        meta: { permission: ['categories.manage', 'courses.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.categories', to: '/categories' }, { key: 'categories.edit.title' }], titleKey: 'categories' },        props: route => ({ idEdit: Number(route.params.idEdit) }),
        //props: true
    },
    //
    // Блок файлов
    //
    {
        path: '/files/add',
        component: () => import('../Pages/FileLoadSimple.vue'),
        name: 'files.simple',
        meta: { permission: ['files.upload', 'courses.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.files' }], titleKey: 'files' },    },
    {
        path: '/calendar',
        meta: { permission: ['exams.manage', 'grading.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.calendar' }], titleKey: 'calendar' },
        component: () => import('../Pages/EventCalendar.vue'),
        name: 'calendar',
    },
    {
        // Запись групп на курсы. id в конце опционален: пункт меню ведёт
        // на /group/learning без id, и группу выбирают в форме — раньше
        // был жёстко зашит /group/learning/1, то есть записать можно
        // было только группу №1.
        path: '/group/learning/:idEdit?',
        component: () => import('../Pages/Group/GroupLearning.vue'),
        props: route => ({ idEdit: toIntOrNull(route.params.idEdit) }),
        name: 'group.learning',
        meta: { permission: ['users.courses'] },    },
    {
        // Учебный план обучаемого: его собственные назначения.
        // Отдельная страница вместо админской формы записи групп
        // (/group/learning, право users.courses), которая отдавала 403.
        path: '/my/learning',
        meta: { breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.myLearning' }], titleKey: 'myLearning' },
        component: () => import('../Pages/Learning/MyLearningPlan.vue'),
        name: 'learning.plan',
    },
    {
        // Экзамены обучаемого: что назначено, когда открыто, сколько попыток.
        path: '/my/exams',
        meta: { breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.exams' }], permission: ['exams.take', 'exams.manage'], titleKey: 'exams' },
        component: () => import('../Pages/Exam/ExamList.vue'),
        name: 'exams.mine',
    },
    {
        // Прохождение экзамена.
        path: '/exams/:idEdit',
        meta: { breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.exams', to: '/my/exams' }, { key: 'exams.runner.title' }], permission: ['exams.take', 'exams.manage'], titleKey: 'exams' },
        component: () => import('../Pages/Exam/ExamRunner.vue'),
        name: 'exams.take',
        props: route => ({ idEdit: Number(route.params.idEdit) }),
    },
    {
        // Дашборд обучаемого в структуре классического LMS:
        // показатели, дедлайны, экзамены, мои курсы.
        path: '/dashboard',
        meta: { breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.dashboard' }], titleKey: 'dashboard' },
        component: () => import('../Pages/Dashboard/TraineeDashboard.vue'),
        name: 'dashboard',
    },
    {
        path: '/auk',
        meta: { permission: ['courses.view'], titleKey: 'dashboard' },
        component: () => import('../Pages/User/UserPage.vue'),
        name: 'index',
    },
    //--------------------------- блок вопросов-----------------------------------
    {
        path: '/upload-gift',
        component: () => import('../Pages/Gift/GiftImportForm.vue'),
        name: 'upload-gift',
        meta: { permission: ['questions.manage'], titleKey: 'questionbank' },    },
    {
        path: '/questions',
        // Прохождение экзамена — exams.take (обучаемый),
        // questions.view/manage — работа с банком вопросов (методист).
        // Раньше здесь стояли только questions.*, поэтому «Экзамены»
        // были недоступны обучаемому, хотя право exams.take у него есть.
        meta: { permission: ['exams.take', 'questions.view', 'questions.manage'] },
        component: () => import('../Pages/Gift/ExamineItem.vue'),
        props: (route) => ({
            idEdit: toIntOrNull(route.query.idEdit),
            idCategory: toIntOrNull(route.query.idCategory),
        }),
        name: 'questions',
    },
    {
        path: '/questions-main',
        component: () => import('../Pages/Gift/ExamineMain.vue'),
        props: (route) => ({
            idEdit: toIntOrNull(route.query.idEdit),
            idCategory: toIntOrNull(route.query.idCategory),
        }),
        name: 'questions.main',
        meta: { permission: ['questions.view', 'questions.manage'], breadcrumbs: [{ key: 'app.title', to: '/' }, { key: 'app.menu.questionbank' }], titleKey: 'questionbank' },    },
    {
        path: '/questions-main/:idEdit',
        component: () => import('../Pages/Gift/QuestionEdit.vue'),
        props: route => ({ idEdit: Number(route.params.idEdit) }),
        name: 'question.edit',
        meta: { permission: ['questions.manage'] },    },
    // {
    //     path: '/questions-main/:idEdit?',
    //     component: () => import('../Pages/Gift/QuestionEdit.vue'),
    //     props: route => ({ idEdit: route.params.idEdit ? Number(route.params.idEdit) : null }),
    //     name: 'question.edit'
    // },
    {
        //        path: '/questions/:idEdit',
        path: '/questions',
        meta: { permission: ['questions.view', 'questions.manage'] },
        component: () => import('../Pages/Gift/QuestionItem.vue'),
        // props: route => ({ idEdit: Number(route.params.idEdit) }),
        props: (route) => ({
            idEdit: toIntOrNull(route.query.idEdit),
            idCategory: toIntOrNull(route.query.idCategory),
        }),
        name: 'question.item',
    },


    // {
    //     // тестовый вариант открытия страницы(манифест)
    //     path: '/courses/itemmani',
    //     component: () => import('../Pages/CourseManifest.vue'),
    //     // props: route => ({ idEdit: Number(route.params.idEdit)}),
    //     props: (route) => ({
    //         idEdit: toIntOrNull(route.query.idEdit),
    //         idCategory: toIntOrNull(route.query.idCategory),
    //     }),
    //     name: 'courses.itemmani',

    // },

    {
        //path: '/questions-main/new',
        path: '/questions-main/new/:category_id/:aukstructure_id',
        component: () => import('../Pages/Gift/QuestionNew.vue'),
        //props: route => ({ category_id: Number(route.params.category_id), aukstructure_id: Number(route.params.aukstructure_id) }),
        // props: (route) => ({
        //     category_id: toIntOrNull(route.query.current_category),
        //     aukstructure_id: toIntOrNull(route.query.current_auk_theme),
        // }),
        props: (route) => ({
            category_id: Number(route.params.category_id),
            aukstructure_id: Number(route.params.aukstructure_id)
        }),
        name: 'question.new',
        meta: { permission: ['questions.manage'] },    },
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
        meta: { permission: ['files.upload', 'content.manage'] },
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
        meta: { permission: ['users.courses'] },
        name: 'userCourse',
        component: () => import('../Pages/User/UserCourse.vue'),
        props: route => ({ id: Number(route.params.id) }),
    },
    // блок настроек
    {
        path: '/grade-boundary/',
        name: 'gradeBoundary',
        meta: { permission: ['settings.manage'] },        component: () => import('../Pages/Settings/GradeSettings.vue'),
    },
    {
        path: '/settings/',
        name: 'allSettings',
        meta: { permission: ['settings.manage'] },        component: () => import('../Pages/Settings/AllSettings.vue'),
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


