## Vuex хранилище (Vuex 4)

> **Актуализация (2026-10-28), сверено с кодом `resources/js/Store/`:** документ был
> целевым контрактом; ниже — фактическое состояние. Точного списка мутаций из
> прежней версии («оптимистичные апдейты», «кэш списков с TTL 5 минут») в коде нет:
> стейт-модули хранят плоские списки, нормализации `{ids, entities}` нет, кэширование
> по ключу фильтров не реализовано. Отдельных модулей `groups` и `courses.byId` тоже
> нет — группы живут внутри `UserModule`, курс одного уровня — `CourseModule.course`.

Модули (регистрация — `resources/js/Store/index.js`, все `namespaced: true`)
- **auth** (`AuthModule.js`): accessToken, user, permissionSlugs, roleSlugs;
  actions: login, fetchCurrentUser (GET /api/v1/me — источник истины о правах после
  перезагрузки), verifyTwoFactor, logout
- **courses** (`CourseModule.js`): aircrafts, courses, categories, modules (aukstructures),
  totalCourses, pagination{page,perPage,total,totalPages}; actions: fetchCourses,
  fetchCoursesFilter, fetchCourse, fetchAircrafts, fetchAircraft, fetchModules,
  fetchCategories, fetchCategory, createCourse, updateCourse, deleteCourse,
  createCategory, updateCategory
- **users** (`UserModule.js`): users, pagination, groups/allGroups/totalGroups, group,
  allPermissions, permissionGroups, manageableUsers, permissionScope; actions: fetchUsers,
  fetchUser, createUser, updateUser, deleteUser, chpassUser, fetchGroups, fetchGroup,
  createGroup, updateUserPermissions, fetchPermissionCatalog, fetchManageableUsers, registerUser
- **exam** (`ExamModule.js`): exams/pagination; actions: fetchExams, fetchExam, createExam,
  updateExam, fetchModuleStats, fetchCoursesByCategory, fetchCategories
- **ui** (`UiModule.js`): menudrawler, language, density (persist через LocalStorage);
  тосты/диалоги в сторе НЕ живут — они вынесены в composable `useToast`
- **userPage** (`userPage.store.js`): состояние персональной страницы

Состояние и ошибки
- Списки — массивы в state; пагинация серверная через конверт `meta.pagination`
  (разворачивание ответа — хелперы `resources/js/api/envelope.js`).
- Ошибки action'ов обрабатываются на месте (try/catch + тост/extractFieldErrors),
  поля `lastError` на модуль нет.

Кэширование
- В сторе — отсутствует (списки перезапрашиваются при каждом визите маршрута).
- Единственный клиентский кэш тяжёлых данных — `utils/docCache.js` (LRU документов
  плеера, 15 шт.) и серверный кэш дерева плеера; см. `docs/course-player-performance.md`.

Правило для нового кода: состояние плеера v2 держать вне Vuex — в composables
(`useLessonLoader` и пр.), store хранит только справочники (см. blueprint §2.2).


