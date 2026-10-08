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
        // Пункт только для обучаемого.
        //
        // /my/learning открыт любому вошедшему (это его собственные записи
        // на курсы), но показывать его имеет смысл только обучающемуся: у
        // методиста записей нет и страница открывается пустой.
        onlyFor: 'trainee',
      },
      { key: 'tutor', titleKey: 'app.menu.tutor', link: '/tutor', icon: 'mdi-brain' },
      { key: 'exams', titleKey: 'app.menu.exams', link: '/my/exams', icon: 'mdi-clipboard-check-outline' },
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
      { key: 'courses', titleKey: 'app.menu.courses', link: '/courses/list', icon: 'mdi-book-open-page-variant-outline' },
      { key: 'classes', titleKey: 'app.menu.classes', link: '/classes', icon: 'mdi-file-tree-outline' },
      { key: 'questionbank', titleKey: 'app.menu.questionbank', link: '/questions-main', icon: 'mdi-help-circle-outline' },
      { key: 'categories', titleKey: 'app.menu.categories', link: '/categories', icon: 'mdi-domain' },
      // Пункт ведёт в файловый менеджер, а не на страницу простой
      // загрузки. /files/add остаётся отдельным маршрутом: его
      // подключают формы курсов как компонент (FileLoadSimple.vue),
      // и он не нужен как самостоятельный раздел.
      { key: 'files', titleKey: 'app.menu.files', link: '/filemanager', icon: 'mdi-folder-multiple-outline' },
      { key: 'calendar', titleKey: 'app.menu.calendar', link: '/calendar', icon: 'mdi-calendar-month' },
      { key: 'learning', titleKey: 'app.menu.learning', link: '/group/learning', icon: 'mdi-account-group-outline' },
    ],
  },
  {
    key: 'admin',
    items: [
      { key: 'users', titleKey: 'app.menu.users', link: '/user/list', icon: 'mdi-account-multiple-outline' , activeMatch: '/user' },
      { key: 'groups', titleKey: 'app.menu.groups', link: '/groups/list', icon: 'mdi-account-group-outline' , activeMatch: '/groups' },
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
 */
export const accountItems = [
  { key: 'profile', titleKey: 'account.items.profile', link: '/my', icon: 'mdi-account-outline' },
  { key: 'plan', titleKey: 'account.items.plan', link: '/my/learning', icon: 'mdi-school-outline' },
  { key: 'tutor', titleKey: 'app.menu.tutor', link: '/tutor', icon: 'mdi-brain' },
  { key: 'settings', titleKey: 'account.items.settings', link: '/settings/', icon: 'mdi-cog-outline' },
]

export default navigationSections