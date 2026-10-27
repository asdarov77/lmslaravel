import httpClient from './httpClient'

const fetchUsers = (params = {}) => httpClient.post('/api/user/list', params)
const fetchUser = (id) => httpClient.get(`/api/user/list/${id}`)
const updateUser = (id, data) => httpClient.patch(`/api/user/${id}`, data)
/*
 * Публичная саморегистрация (страница входа, вкладка «Регистрация»).
 *
 * Это НЕ то же самое, что createUser ниже: сюда приходят посторонние,
 * поэтому эндпоинт открыт и не требует прав. Смешивать эти два вызова
 * нельзя — иначе либо регистрация уходит под право `users.create`,
 * либо создание из админки уходит в публичную форму без роли и группы.
 */
const registerUser = data => httpClient.post('/api/register', data)

/*
 * Создание пользователя администратором.
 *
 * Раньше здесь стояло `POST /api/register` — публичная форма, которой
 * пользуются посторонние. Она не входит в авторизованную группу, не
 * проверяет право `users.create` и не даёт задать роль или группу. То
 * есть «создать пользователя из админки» физически не было возможным:
 * кнопка вела в чужую форму.
 *
 * Настоящий эндпоинт — `POST /api/v1/users` (AuthController::store),
 * закрыт правом `users.create` и принимает роль и группу.
 */
const createUser = data => httpClient.post('/api/v1/users', data)
const deleteUser = id => httpClient.delete(`/api/user/${id}`)
const fetchGroups = () => httpClient.get('/api/groups')
const fetchGroup = (id) => httpClient.get(`/api/groups/${id}`)
const fetchPermissions = () => httpClient.get('/api/permissions')
// Каталог прав по разделам (группы берёт бэкенд из config/permissions.php).
// В ответе у каждого права есть assignable — можно ли его выдать текущему
// пользователю: администратору всё, инструктору только его собственный набор.
const fetchPermissionCatalog = () => httpClient.get('/api/permissions/catalog')
// Пользователи, чьи права текущий может менять (страница управления правами).
const fetchManageableUsers = () => httpClient.get('/api/user/manageable')
const createGroup = data => httpClient.post('/api/groups', data)
const updateGroup = (id, data) => httpClient.put(`/api/groups/${id}`, data)
const deleteGroup = id => httpClient.delete(`/api/groups/${id}`)
// смена пароля
const chpassUser = (id, data) => httpClient.put(`/api/user/chpass/${id}`, data)
// назначение прав пользователю (бэкенд: PUT /api/user/chperm/{id}, permission:users.permissions)
const updateUserPermissions = (id, permissionIds) => httpClient.put(`/api/user/chperm/${id}`, { permission_id: permissionIds })

export {
  registerUser,
  fetchUsers,
  fetchUser, 
  fetchGroup,
  updateUser,
  createUser,
  deleteUser,
  fetchGroups,
  fetchPermissions,  
  fetchPermissionCatalog,
  fetchManageableUsers,
  createGroup,
  updateGroup,
  deleteGroup,
  chpassUser,
  updateUserPermissions
}
