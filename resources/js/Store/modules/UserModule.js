
import {
  fetchUsers,
  fetchUser,
  fetchGroups,
  fetchGroup,
  fetchPermissions,  
  createGroup,
  updateGroup,
  createUser,
  updateUser,
  deleteGroup,
  deleteUser,
  chpassUser
} from '../../api/user.api'




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
            state.users = users
          },
          SET_USERS_PAGINATION(state, pagination) {
            state.pagination = pagination
          },
          SET_TOTAL_USERS(state, totalUsers) {
            state.totalUsers = totalUsers
          },
          SET_ALL_GROUPS(state, allGroups) {
            state.allGroups = allGroups
          },
          SET_TOTAL_GROUPS(state, totalGroups) {
            state.totalGroups = totalGroups
          },
          SET_ALL_PERMISSIONS(state, allPermissions) {
            state.allPermissions = allPermissions
          },
          SET_GROUP(state, group) {
            state.group = group
          },
          SET_USER(state, user) {
            state.user = user    
          },
          UPDATE_GROUP(state, payload) {
            const itemIdx = state.allGroups.findIndex(item => item.id === payload.id)
            Object.keys(payload).forEach(key => {
              state.allGroups[itemIdx][key] = payload[key]
            })
          },
          UPDATE_USER(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
            DELETE_USER(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
        
          CHANGE_USER_PASSWORD(state, payload) {
            const itemIdx = state.users.findIndex(item => item.id === payload.id)
            Object.keys(payload).forEach(key => {
              state.users[itemIdx][key] = payload[key]
            })
          },
          // DELETE_GROUP(state, id) {
          //   const group_id = state.allGroups.findIndex(g => g.id === id)
          //   console.log(state.allGroups.length)
          //   state.allGroups.splice(group_id, 1)
          //   console.log(state.allGroups.length)
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
              const items = response.data.data || response.data
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
              //console.log(response, 'fetchuser')          
              //commit('SET_TOTAL_USERS', response.data.length)      
              commit('SET_USER', response.data)
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
              //console.log(response, 'groups')          
              commit('SET_TOTAL_GROUPS', response.data.length)
              commit('SET_ALL_GROUPS', response.data)                     
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async fetchGroup({ commit },id ) {
            try {
              //params = { ...params, exclude_by_name: 'SysAdmin' } 
              const response = await fetchGroup(id)
              //console.log(response, 'fetchGroup')
              commit('SET_GROUP', response.data)      
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async fetchPermissions({ commit }) {
            try {
              const response = await fetchPermissions()
              //console.log(response)
              commit('SET_ALL_PERMISSIONS', response.data)
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
        
          async updateUser({ commit, state }, { id, data }) {
            try {
              const idx = state.users.findIndex(u => u.id === id)
              const previous = idx >= 0 ? { ...state.users[idx] } : null
              if (idx >= 0) {
                commit('UPDATE_USER', { id, ...data })
              }
              const response = await updateUser(id, data)
              commit('UPDATE_USER', response.data.data || response.data)
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
              commit('SET_USER', response.data)
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          async createGroup({ commit }, data) {
            try {      
              const response = await createGroup(data)      
              commit('SET_ALL_GROUPS', response.data)      
              return Promise.resolve(response)
            } catch (error) {
            //console.log('error console', error)
              return Promise.reject(error)
            }
          },
          async updateGroup({ commit }, { id, data }) {
            try {
              const response = await updateGroup(id, data)
              commit('UPDATE_GROUP', response.data)
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
              //console.log(response, 'chpassUser')
              commit('CHANGE_USER_PASSWORD', response.data)
              return Promise.resolve(response)
            } catch (error) {
              return Promise.reject(error)
            }
          },
          // async removeUserGroup({ commit }, user, group_id) {
          //   try {
          //     const userIdx = state.users.findIndex(u => u.id === user.id)
          //     console.log(state.users[userIdx])
          //     commit('REMOVE_USER_GROUP', userIdx, group_id)
          //     console.log(state.users[userIdx])
          //     const response = await updateUser(user.id, state.users[userIdx])
          //     return Promise.resolve(response)
          //   } catch (error) {
          //     return Promise.reject(error)
          //   }
          // },
          // async deleteGroupPermission({ commit }, group, permIdx) {
          //   try {
          //     const group_id = state.allGroups.findIndex(g => g.id === group.id)
          //     console.log(state.allGroups[group_id])
          //     commit('DELETE_GROUP_PERMISSION', group_id, permIdx)
          //     console.log(state.allGroups[group_id])
          //     const response = await updateGroup(group.id, state.allGroups[group_id])
          //     return Promise.resolve(response)
          //   } catch (error) {
          //     return Promise.reject(error)
          //   }
          // }


    },
    getters: {

        users(state) {
            //console.log(state,'state+')
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