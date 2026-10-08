
import {
  fetchUsers,
  fetchUser,
  fetchGroups,
  fetchGroup,
  fetchPermissions,
  fetchPermissionCatalog,
  fetchManageableUsers,
  updateUserPermissions,  
  createGroup,
  updateGroup,
  createUser,
  updateUser,
  deleteGroup,
  deleteUser,
  chpassUser
} from '../../api/user.api'
import { unwrapResponse as unwrap, asArray } from '../../api/envelope'

const UserModule = {
    namespaced: true,
    state: () => ({

        users: [],
        pagination: { page: 1, perPage: 15, total: 0, totalPages: 0 },
        groups: [],
        totalUsers: 0,
        allGroups: [],
        totalGroups: 0,
        allPermissions: [],
        // Каталог прав по разделам + пользователи, чьи права текущий
        // вправе менять. Нужны отдельной странице управления правами.
        permissionGroups: [],
        manageableUsers: [],
        permissionScope: null,
      
        group: {
          id: null,
          groupname: '',
          groupdescription: '',
          study_from: '',
          study_to: '',
          group2learnings: []
          //permissions: [],
          //permissions_id: []
        },
        user: {
          id: null,
          fio: '',
          role: '',
          group_id: '',
          permissions: [],
          //categories: []
        }  

    }),
    mutations: {
        
        // RESET(state) {
        //     const newState = initialState()
        //     Object.keys(newState).forEach(key => {
        //       state[key] = newState[key]
        //     })
        //   },
        
          SET_USERS(state, users) {    
            state.users = asArray(users)
          },
          SET_USERS_PAGINATION(state, pagination) {
            state.pagination = pagination
          },
          SET_TOTAL_USERS(state, totalUsers) {
            state.totalUsers = totalUsers
          },
          SET_ALL_GROUPS(state, allGroups) {
            // Приводим к массиву: иначе .map/.sort в шаблонах упадут,
            // если в state попал конверт { success, data, error, meta } или null
            state.allGroups = asArray(allGroups)
          },
          SET_TOTAL_GROUPS(state, totalGroups) {
            state.totalGroups = totalGroups
          },
          SET_ALL_PERMISSIONS(state, allPermissions) {
            state.allPermissions = asArray(allPermissions)
          },
          SET_PERMISSION_GROUPS(state, groups) {
            state.permissionGroups = asArray(groups)
          },
          SET_MANAGEABLE_USERS(state, users) {
            state.manageableUsers = asArray(users)
          },
          SET_GROUP(state, group) {
            state.group = group
          },
          SET_USER(state, user) {
            // Регресс: при user === null шаблон UserItemEdit падал на this.user.permissions
            state.user = user && typeof user === 'object' ? user : { ...state.user }
          },
          UPDATE_GROUP(state, payload) {
            const itemIdx = state.allGroups.findIndex(item => item.id === payload.id)
            if (itemIdx < 0 || !payload) return
            Object.keys(payload).forEach(key => {
              state.allGroups[itemIdx][key] = payload[key]
            })
          },
          UPDATE_USER(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            if (itemIdx < 0 || !payload) return
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
            DELETE_USER(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            if (itemIdx < 0 || !payload) return
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
        
          CHANGE_USER_PASSWORD(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            if (itemIdx < 0 || !payload) return
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
          // DELETE_GROUP(state, id) {
          //   const group_id = state.allGroups.findIndex(g => g.id === id)
          //   state.allGroups.splice(group_id, 1)
          // },
          // REMOVE_USER_GROUP(state, userIdx, group_id) {
          //   state.users[userIdx].groups.splice(group_id, 1)
          //   state.users[userIdx].groups_ids.splice(group_id, 1)
          // },
          // DELETE_GROUP_PERMISSION(state, group_id, permIdx) {
          //   state.allGroups[group_id].permissions.splice(permIdx, 1)
          //   state.allGroups[group_id].permissions_ids.splice(permIdx, 1)
          // }

        
    },
    actions: {
        
        async fetchUsers({ commit, state }, params = {}) {

            try {      
              const response = await fetchUsers(params)
              const items = asArray(unwrap(response))
              const pag = (response.data.meta && response.data.meta.pagination) || state.pagination
              commit('SET_TOTAL_USERS', pag.total || items.length)      
              commit('SET_USERS_PAGINATION', pag)
              commit('SET_USERS', items)
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
        
          async fetchUser({ commit },id ) {
        
            try {      
              const response = await fetchUser(id)
              //commit('SET_TOTAL_USERS', response.data.length)      
              commit('SET_USER', unwrap(response))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
        
        
          // FIX exclude_by_name !!!!!
          async fetchGroups({ commit }) {
            try {
              //params = { ...params, exclude_by_name: 'SysAdmin' } 
              const response = await fetchGroups()
              const groups = asArray(unwrap(response))
              commit('SET_TOTAL_GROUPS', groups.length)
              commit('SET_ALL_GROUPS', groups)                     
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async fetchGroup({ commit },id ) {
            try {
              //params = { ...params, exclude_by_name: 'SysAdmin' } 
              const response = await fetchGroup(id)
              commit('SET_GROUP', unwrap(response))      
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async updateUserPermissions({ dispatch }, { id, permissionIds }) {
            try {
              // import назван идентично экшну — обращаемся через импортированный модуль,
              // иначе this.updateUserPermissions рекурсивно вызовет сам экшн.
              const api = await import('../../api/user.api')
              const response = await api.updateUserPermissions(id, permissionIds)
              // Если меняли права себе — синхронизируем Auth-стор,
              // иначе меню/гарды будут жить по устаревшему набору прав.
              await dispatch('Auth/fetchCurrentUser', null, { root: true })
                .catch(() => {})
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          /**
           * Каталог прав по разделам для страницы управления правами.
           * Бэкенд проставляет assignable по роли актора, поэтому
           * интерфейс не решает сам, что можно выдать.
           */
          async fetchPermissionCatalog({ commit }) {
            try {
              const response = await fetchPermissionCatalog()
              commit('SET_PERMISSION_GROUPS', asArray(unwrap(response)))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },

          async fetchManageableUsers({ commit }) {
            try {
              const response = await fetchManageableUsers()
              commit('SET_MANAGEABLE_USERS', asArray(unwrap(response)))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },

          async fetchPermissions({ commit }) {
            try {
              const response = await fetchPermissions()
              commit('SET_ALL_PERMISSIONS', asArray(unwrap(response)))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
        
          async updateUser({ commit, state }, { id, data }) {
            let previous = null
            let idx = -1
            try {
              idx = state.users.findIndex(u => u.id === id)
              previous = idx >= 0 ? { ...state.users[idx] } : null
              if (idx >= 0) {
                commit('UPDATE_USER', { id, ...data })
              }
              const response = await updateUser(id, data)
              commit('UPDATE_USER', unwrap(response))
              return Promise.resolve(response)
            } catch (error) {
              if (previous) {
                commit('UPDATE_USER', previous)
              }
              return Promise.reject(error)
            }
          },
          async createUser({ commit }, data) {
            try {
              const response = await createUser(data)
              const payload = unwrap(response)
              commit('SET_USER', payload && payload.user ? payload.user : payload)
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async createGroup({ commit, state }, data) {
            try {      
              const response = await createGroup(data)      
              const group = unwrap(response)
              commit('SET_ALL_GROUPS', asArray(state.allGroups).concat(group ? [group] : []))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async updateGroup({ commit }, { id, data }) {
            try {
              const response = await updateGroup(id, data)
              commit('UPDATE_GROUP', unwrap(response))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async deleteGroup({ commit }, id) {
            try {
              await deleteGroup(id)
              commit('DELETE_GROUP', id)
              return Promise.resolve()
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async deleteUser({ commit }, id) {
            try {
              await deleteUser(id)
              commit('DELETE_USER', id)          
              return Promise.resolve()
            } catch (error) {
              return Promise.reject(error)
            }
          },
        
          async chpassUser({ commit }, { id, data }) {
            try {
              const response = await chpassUser(id, data)
              commit('CHANGE_USER_PASSWORD', unwrap(response))
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          // async removeUserGroup({ commit }, user, group_id) {
          //   try {
          //     const userIdx = state.users.findIndex(u => u.id === user.id)
          //     commit('REMOVE_USER_GROUP', userIdx, group_id)
          //     const response = await updateUser(user.id, state.users[userIdx])
          //     return Promise.resolve(response)
          //   } catch (error) {
          //     return Promise.reject(error)
          //   }
          // },
          // async deleteGroupPermission({ commit }, group, permIdx) {
          //   try {
          //     const group_id = state.allGroups.findIndex(g => g.id === group.id)
          //     commit('DELETE_GROUP_PERMISSION', group_id, permIdx)
          //     const response = await updateGroup(group.id, state.allGroups[group_id])
          //     return Promise.resolve(response)
          //   } catch (error) {
          //     return Promise.reject(error)
          //   }
          // }


    },
    getters: {

        users(state) {
            return state.users.map(user => {
              return {
                id: user.id,
                fio: user.fio,
                role: user.role,
                group_id: user.group_id,        
                //isAuthenticated: user.isAuthenticated,        
                group: user.group?user.group.groupname:'',
                phonenumber: user.phonenumber,
                city: user.city,
                country: user.country,
                organization: user.organization,
                position: user.position,
                rank: user.rank,
                spfere: user.spfere,
                specialization: user.specialization,
                permissions: user.permissions,
                //categories: user.categories,
        
                // name: `${user.last_name} ${user.first_name} ${user.mid_name}`,        
              }
            })
          },
          user(state){
            return {
              id: null,
              fio: '',
              role: '',
              group_id: '',      
              permissions: [],
             // categories: []
            }
          },
          groups(state) {    
            return state.allGroups.map(group => {
              return {
                id: group.id,
                groupname: group.groupname,
                groupdescription: group.groupdescription,
                group2learnings : group.group2learnings,
        //        study_from: group.study_from,
        //        study_to: group.study_to,
                courses: group.courses,        
              }
            })
          }


    }
}

export default UserModule;