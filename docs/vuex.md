## Vuex хранилище (Vuex 4)

Модули
- auth: token, profile; actions: login, logout, fetchProfile
- courses: list, byId, filters, pagination; actions: fetchCourses, fetchCoursesFilter, fetchCourse, createCourse, updateCourse, deleteCourse
- users: list, byId; actions: fetchUsers, fetchUser, createUser, updateUser, deleteUser, chpassUser, fetchGroups, fetchGroup
- groups: list, enrollments; actions: fetchGroups, enroll, unenroll
- ui: toasts, dialogs, loading states

Состояние и нормализация
- list: { ids: [], entities: { [id]: Course } }
- pagination: { page, perPage, total, totalPages }
- Ошибки: lastError на модуль, очищается в начале action

Оптимистичные апдейты
- enroll/unenroll: локальное обновление + rollback при ошибке
- updateCourse/updateUser: предварительное обновление записи и откат при ошибке

Кеширование
- Списки по ключу фильтров, TTL 5 минут; инвалидация при изменениях

Мутации и геттеры (основные)
- courses.mutations: SET_COURSES, SET_COURSE, SET_TOTAL_COURSES, SET_PAGINATION, UPDATE_COURSE, DELETE_COURSE
- users.mutations: SET_USERS, SET_USER, SET_TOTAL_USERS, SET_USERS_PAGINATION, UPDATE_USER, DELETE_USER
- courses.getters: courses (список нормализованных полей)
- users.getters: users (список нормализованных полей)


