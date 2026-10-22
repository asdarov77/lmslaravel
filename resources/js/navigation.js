/**
 * Структура меню.
 *
 * Здесь ТОЛЬКО группировка, порядок, подпись, иконка и адрес. Прав НЕ
 * хранится — они берутся из `meta.permission` соответствующего маршрута
 * (см. ROUTE_PERMISSIONS в LeftSideMenu.vue).
 *
 * Почему так: требования к пункту и к странице — это одно и то же право.
 * Дублируя право здесь, мы получили бы третий источник правды (вместе с
 * meta.permission и middleware), и рано или поздно меню показало бы
 * пункт, который ведёт в 403, — ровно тот баг, который уже был в проекте
 * с «Классами» и «Календарём».
 *
 * Секция скрывается, когда её видно меньше двух пунктов: одна строка
 * заголовка без содержимого выглядит как недогруженная страница.
 *
 * Пункт может содержать `children` — вложенные подэлементы, которые
 * рендерятся внутри родительского пункта с большим отступом и своей
 * подсветкой активного состояния.
 */

export const navigationSections = [
  {
    key: 'learning',
    items: [
      { key: 'dashboard', titleKey: 'app.menu.dashboard', link: '/dashboard', icon: 'mdi-view-dashboard-outline' },
      {
        key: 'myLearning',
        titleKey: 'app.menu.myLearning',
        link: '/my/learning',
        icon: 'mdi-calendar-month-outline',
        onlyFor: 'trainee',
      },
      // Вложенные подпункты есть только там, где за разделом стоят
      // РЕАЛЬНЫ разные адреса. Подразделы Tutor и «Банк вопросов»
      // намеренно оставлены плоскими: маршрутов /tutor/materials,
      // /tutor/sessions и /questions/new в роутере нет, а пункт меню,
      // ведущий в пустоту, хуже отсутствующего пункта.
      { key: 'tutor', titleKey: 'app.menu.tutor', link: '/tutor', icon: 'mdi-brain' },
      { key: 'announcements', titleKey: 'app.menu.announcements', link: '/announcements', icon: 'mdi-bullhorn-outline' },
      { key: 'assignments', titleKey: 'app.menu.assignments', link: '/assignments', icon: 'mdi-clipboard-text-outline', badgeCounter: 'review_queue' },
      { key: 'exams', titleKey: 'app.menu.exams', link: '/my/exams', icon: 'mdi-clipboard-check-outline' },
      { key: 'examsManage', titleKey: 'app.menu.examsManage', link: '/exams', icon: 'mdi-clipboard-list-outline', onlyFor: 'manager' },
      { key: 'certificates', titleKey: 'app.menu.certificates', link: '/my/certificates', icon: 'mdi-certificate-outline', onlyFor: 'trainee' },
      { key: 'gamification', titleKey: 'app.menu.gamification', link: '/gamification', icon: 'mdi-chart-line', onlyFor: 'trainee' },
    ],
  },
  {
    key: 'catalog',
    items: [
      { key: 'catalog', titleKey: 'app.menu.catalog', link: '/catalog', icon: 'mdi-bookshelf' },
    ],
  },
  {
    key: 'staff',
    items: [
      {
        key: 'courses',
        titleKey: 'app.menu.courses',
        link: '/courses/list',
        icon: 'mdi-book-open-page-variant-outline',
        children: [
          { key: 'coursesAdd', titleKey: 'app.menu.coursesAdd', link: '/course', icon: 'mdi-book-plus-outline', onlyFor: 'manager' },
        ],
      },
      {
        key: 'classes',
        titleKey: 'app.menu.classes',
        link: '/classes',
        icon: 'mdi-file-tree-outline',
      },
      { key: 'questionbank', titleKey: 'app.menu.questionbank', link: '/questions-main', icon: 'mdi-help-circle-outline' },
      { key: 'categories', titleKey: 'app.menu.categories', link: '/categories', icon: 'mdi-domain' },
      { key: 'files', titleKey: 'app.menu.files', link: '/filemanager', icon: 'mdi-folder-multiple-outline' },
      { key: 'calendar', titleKey: 'app.menu.calendar', link: '/calendar', icon: 'mdi-calendar-month' },
      { key: 'learning', titleKey: 'app.menu.learning', link: '/group/learning', icon: 'mdi-account-group-outline' },
      { key: 'gradebook', titleKey: 'app.menu.gradebook', link: '/gradebook', icon: 'mdi-table-large' },
      { key: 'reports', titleKey: 'app.menu.reports', link: '/reports', icon: 'mdi-chart-box-outline' },
      { key: 'whiteLabel', titleKey: 'app.menu.whiteLabel', link: '/white-label', icon: 'mdi-palette' },
      { key: 'certSettings', titleKey: 'app.menu.certSettings', link: '/certificates/settings', icon: 'mdi-file-certificate-outline' },
    ],
  },
  {
    key: 'admin',
    items: [
      {
        key: 'users',
        titleKey: 'app.menu.users',
        link: '/user/list',
        icon: 'mdi-account-multiple-outline',
        activeMatch: '/user',
        // Подпункт «Добавить пользователя» убран: он вёл на /reg —
        // публичную форму регистрации, а не на создание пользователя.
        // Права users.create у формы нет (она для посторонних), роль и
        // группу оттуда задать нельзя, а `store` назначает обучаемого.
        // То есть администратор нажимал «добавить» и попадал в чужую
        // форму. Экрана создания пользователя в роутере нет, а пункт,
        // ведущий не туда, хуже отсутствующего пункта.
      },
      {
        key: 'groups',
        titleKey: 'app.menu.groups',
        link: '/groups/list',
        icon: 'mdi-account-group-outline',
        activeMatch: '/groups',
        children: [
          { key: 'groupsAdd', titleKey: 'app.menu.groupsAdd', link: '/groups/add', icon: 'mdi-plus-circle-outline', onlyFor: 'manager' },
        ],
      },
      { key: 'permissions', titleKey: 'app.menu.permissions', link: '/permissions', icon: 'mdi-shield-key-outline' },
      { key: 'settings', titleKey: 'app.menu.settings', link: '/settings/', icon: 'mdi-cog-outline' },
      { key: 'grades', titleKey: 'app.menu.grades', link: '/grade-boundary/', icon: 'mdi-percent-outline' },
    ],
  },
]

/**
 * Пункты меню профиля.
 *
 * Отдельный список, а не подмножество бокового: профиль — это «про меня»,
 * а не «разделы системы». Права те же, из маршрутов.
 *
 * Раньше здесь стояли «Учебный план», «Тренажёр» и «Настройки» — все три
 * дословно повторяли пункты бокового меню, и выпадающее окно не отвечало
 * ни на один вопрос, которого не решает сайдбар. Теперь здесь только то,
 * что действительно «моё»: личные данные, мои сертификаты, моё расписание
 * и раздел прав для тех, кому он доступен.
 */
export const accountItems = [
  { key: 'profile', titleKey: 'account.items.profile', link: '/my', icon: 'mdi-account-outline' },
  { key: 'certificates', titleKey: 'app.menu.certificates', link: '/my/certificates', icon: 'mdi-certificate-outline' },
  { key: 'schedule', titleKey: 'app.menu.schedule', link: '/my/schedule', icon: 'mdi-calendar-clock-outline' },
  { key: 'permissions', titleKey: 'app.menu.permissions', link: '/permissions', icon: 'mdi-shield-key-outline' },
]

export default navigationSections
