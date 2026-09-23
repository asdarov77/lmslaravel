import httpClient from './httpClient'

const fetchUsers = (params = {}) => httpClient.post('/api/user/list', params)
const fetchUser = (id) => httpClient.get(`/api/user/list/${id}`)
const updateUser = (id, data) => httpClient.patch(`/api/user/${id}`, data)
const createUser = data => httpClient.post('/api/register', data)
const deleteUser = id => httpClient.delete(`/api/user/${id}`)
const fetchGroups = () => httpClient.get('/api/groups')
const fetchGroup = (id) => httpClient.get(`/api/groups/${id}`)
const fetchPermissions = () => httpClient.get('/api/permissions')
const createGroup = data => httpClient.post('/api/groups', data)
const updateGroup = (id, data) => httpClient.put(`/api/groups/${id}`, data)
const deleteGroup = id => httpClient.delete(`/api/groups/${id}`)
// смена пароля
const chpassUser = (id, data) => httpClient.put(`/api/user/chpass/${id}`, data)

export {
  fetchUsers,
  fetchUser, 
  fetchGroup,
  updateUser,
  createUser,
  deleteUser,
  fetchGroups,
  fetchPermissions,  
  createGroup,
  updateGroup,
  deleteGroup,
  chpassUser
}
