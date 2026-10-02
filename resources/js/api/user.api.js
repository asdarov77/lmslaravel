import httpClient from './httpClient'

const fetchUsers = (params = {}) => httpClient.post('/api/user/list', params)
const fetchUser = (id) => httpClient.get(`/api/user/list/${id}`)
const updateUser = (id, data) => httpClient.patch(`/api/user/${id}`, data)
const createUser = data => httpClient.post('/api/register', data)
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
