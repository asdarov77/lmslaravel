import {
  fetchCourses,
  fetchCoursesFilter,
  fetchCourse,
  //--------------
  fetchCourseByCourseAndCategory,
  fetchCourseByCourse,
  fetchCourseByCategory,
  //--------------
  fetchCoursecat,
  fetchCategories,
  fetchCategory,
  //fetchPermissions,  
  createCategory,
  updateCategory,
  deleteCategory,
  createCourse,
  updateCourse,
  deleteCourse,
  //
  //fetchClasses,
  //
  fetchAircraft,
  fetchAircrafts,
  createAircraft,
  updateAircraft,
  deleteAircraft,
  //
  //Group2learning
  //
  fetchGroup2learnings,
  deleteGroup2learnings,
  //fetchLearnings,
} from '../../api/course.api'



import { TokenService } from '../../services/storage.service';
import { UserService } from '../../services/user.service';
// fix !!! login импортированный напрямую из auth.api иначе не работает
import { login } from '../../api/auth.api';
import { unwrapResponse as unwrap, asArray } from '../../api/envelope';

const CourseModule = {
  namespaced: true,
  state: () => ({

    aircrafts: [],
    courses: [],
    categories: [],
    totalCourses: 0,
    pagination: {
      page: 1,
      perPage: 15,
      total: 0,
      totalPages: 0,
    },
    //allGroups: [],
    totalCategories: 0,
    //allPermissions: [],
    aircraft: {
      id: null,
      title: '',
      path: null,
    },

    category: {
      id: null,
      title: '',
      description: '',
      parent_id: null,
      //permissions: [],
      //courses: [],
      //permissions_id: []
    },
    course: {
      id: null,
      title: '',
      short_description: '',
      long_description: '',
      path: '',
      aircraft_id: '',
      //    path_hash: '',
      visible: true,
    },

    //------------------
    group2learnings: [],
    group2learning: {
      id: null,
      course_id: null,
      group_id: null,
      category_id: null,
      parent_id: null,
      teacher: '',
      typeOfLesson: '',
      study_from: '',
      study_to: '',
    },
    //------------------

  }),
  mutations: {



    RESET(state) {
      const newState = initialState()
      Object.keys(newState).forEach(key => {
        state[key] = newState[key]
      })
    },

    SET_COURSES(state, courses) {
      state.courses = asArray(courses)
    },
    SET_COURSE(state, course) {
      state.course = course
    },
    SET_TOTAL_COURSES(state, totalCourses) {
      state.totalCourses = totalCourses
    },
    SET_PAGINATION(state, pagination) {
      state.pagination = pagination
    },
    SET_ALL_CATEGORIES(state, categories) {
      state.categories = asArray(categories).slice().sort((a, b) => parseFloat(a.id) - parseFloat(b.id))
    },
    SET_TOTAL_CATEGORIES(state, totalCategories) {
      state.totalCategories = totalCategories
    },
    SET_AIRCRAFTS(state, aircrafts) {
      state.aircrafts = asArray(aircrafts)
    },
    SET_AIRCRAFT(state, aircraft) {
      state.aircraft = aircraft
    },
    SET_TOTAL_AIRCRAFTS(state, totalAircrafts) {
      state.totalAircrafts = totalAircrafts
    },
    // SET_ALL_PERMISSIONS(state, allPermissions) {
    //   state.allPermissions = allPermissions
    // },
    SET_CATEGORY(state, category) {
      state.category = category
    },
    // FIXME удалить, если не будет использоваться
    // UPDATE_CATEGORY(state, payload) {
    // state.categories = payload
    // const itemIdx = state.categories.findIndex(item => item.id === payload.id)
    // Object.keys(payload).forEach(key => {
    //   state.categories[itemIdx][key] = payload[key]
    // })
    // },
    UPDATE_CATEGORY(state, payload) {
      const itemIdx = state.categories.findIndex(item => item.id === payload.id)
      if (itemIdx < 0) return
      Object.keys(payload).forEach(key => {
        state.categories[itemIdx][key] = payload[key]
      })
    },
    UPDATE_CURRENT_CATEGORY(state, payload) {
      state.category = { ...state.category, ...payload }
    },
    UPDATE_COURSE(state, payload) {
      const itemIdx = state.courses.findIndex(item => item.id === payload.id)
      if (itemIdx < 0 || !payload) return
      Object.keys(payload).forEach(key => {
        state.courses[itemIdx][key] = payload[key]
      })
    },
    DELETE_COURSE(state, payload) {
      const itemIdx = state.courses.findIndex(item => item.id === payload.id)
      if (itemIdx < 0 || !payload) return
      Object.keys(payload).forEach(key => {
        state.courses[itemIdx][key] = payload[key]
      })
    },
    DELETE_CATEGORY(state, payload) {
      const itemIdx = state.categories.findIndex(item => item.id === payload.id)
      if (itemIdx < 0 || !payload) return
      Object.keys(payload).forEach(key => {
        state.categories[itemIdx][key] = payload[key]
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
    SET_GROUP_2_LEARNINGS(state, group2learnings) {
      state.group2learnings = asArray(group2learnings)
    },
    
    DELETE_GROUP_2_LEARNINGS(state, group2learnings) {
      state.group2learnings = asArray(group2learnings)
    },
    SET_ALL_LESSONS(state, categories) {
      state.categories = categories.sort((a, b) => parseFloat(a.id) - parseFloat(b.id))
    },


  },
  actions: {


    async fetchCourses({ commit, state }, params = {}) {

      try {
        const response = await fetchCourses(params)
        const items = asArray(unwrap(response))
        const meta = (response.data && response.data.meta) || {}
        const pag = meta.pagination || state.pagination
        commit('SET_TOTAL_COURSES', pag.total || items.length)
        commit('SET_PAGINATION', pag)
        commit('SET_COURSES', items)
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchCoursesFilter({ commit, state }, params) {

      try {
        const response = await fetchCoursesFilter(params)
        const items = asArray(unwrap(response))
        const meta = (response.data && response.data.meta) || {}
        const pag = meta.pagination || state.pagination
        commit('SET_TOTAL_COURSES', pag.total || items.length)
        commit('SET_PAGINATION', pag)
        commit('SET_COURSES', items)
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },


    // async fetchCourse({ commit },id ) {

    //   try {      
    //     const response = await fetchCourse(id)
    //     //console.log(response, 'fetchCourse+++')                
    //     commit('SET_COURSE', response.data)
    //     return Promise.resolve(response)
    //   } catch (error) {
    //     return Promise.reject(error)
    //   }
    // },

    async fetchCourse({ commit }, payload) {

      try {
        // Раньше сюда передавали число (this.idEdit), а не объект, и
        // деструктуризация { course_id } = 16 давала undefined. При
        // payload = null (страница открыта без ?idEdit=) деструктуризация
        // роняла страницу с TypeError. Нормализуем ввод.
        const { course_id, category_id } = payload && typeof payload === 'object'
          ? payload
          : { course_id: payload ?? null, category_id: null };

        let response;
        if (course_id && category_id) {
          response = await fetchCourseByCourseAndCategory(course_id, category_id);
        } else if (course_id) {
          response = await fetchCourseByCourse(course_id);
        } else if (category_id) {
          response = await fetchCourseByCategory(category_id);
        } else {
          response = await fetchCourses();
        }
        commit('SET_COURSE', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchAircrafts({ commit }) {

      try {
        const response = await fetchAircrafts()
        const aircrafts = asArray(unwrap(response))
        commit('SET_TOTAL_AIRCRAFTS', aircrafts.length)
        commit('SET_AIRCRAFTS', aircrafts)
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchAircraft({ commit }, id) {

      try {
        const response = await fetchAircraft(id)
        commit('SET_AIRCRAFT', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    // async fetchCoursecat({ commit },id ) {

    //   try {      
    //     const response = await fetchCoursecat(id)
    //     //console.log(response, 'fetchCoursecat')                
    //     commit('SET_COURSES', response.data)
    //     return Promise.resolve(response)
    //   } catch (error) {
    //     return Promise.reject(error)
    //   }
    // },

    //-----------------------------------------

    // FIX exclude_by_name !!!!!
    async fetchCategories({ commit }) {
      try {
        //params = { ...params, exclude_by_name: 'SysAdmin' } 
        const response = await fetchCategories()
        const categories = asArray(unwrap(response))
        commit('SET_TOTAL_CATEGORIES', categories.length)
        commit('SET_ALL_CATEGORIES', categories)
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },
    async fetchCategory({ commit }, id) {
      try {
        // Раньше fetchCategory(null) формировал URL /api/categories/null
        // и сервер отвечал 404. Просто не делаем запрос без id.
        if (id === null || id === undefined) {
          commit('SET_CATEGORY', null)
          return Promise.resolve(null)
        }
        const response = await fetchCategory(id)
        commit('SET_CATEGORY', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },
    // async fetchPermissions({ commit }, params) {
    //   try {
    //     const response = await fetchPermissions(params)
    //     commit('SET_ALL_PERMISSIONS', response.data)
    //     return Promise.resolve(response)
    //   } catch (error) {
    //     return Promise.reject(error)
    //   }
    // },

    async updateCourse({ commit, state }, { id, data }) {
      try {
        const idx = state.courses.findIndex(c => c.id === id)
        const previous = idx >= 0 ? { ...state.courses[idx] } : null
        // optimistic
        if (idx >= 0) {
          commit('UPDATE_COURSE', { id, ...data })
        }
        const response = await updateCourse(id, data)
        commit('UPDATE_COURSE', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        // rollback
        if (previous) {
          commit('UPDATE_COURSE', previous)
        }
        return Promise.reject(error)
      }
    },
    async createCourse({ commit }, data) {
      try {
        const response = await createCourse(data)
        commit('SET_COURSE', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },
    async createCategory({ commit, state }, data) {
      try {
        const response = await createCategory(data)
        const category = unwrap(response)
        commit('SET_ALL_CATEGORIES', asArray(state.categories).concat(category ? [category] : []))
        return Promise.resolve(response)
      } catch (error) {
        console.log('error console', error)
        return Promise.reject(error)
      }
    },
    // FIXME удалить, если не будет использоваться
    async updateCategory({ commit }, { id, data }) {
      try {
        const response = await updateCategory(id, data)
        // Синхронизируем и список, и открытую карточку: иначе после
        // возврата на /categories таблица показывала бы старое название.
        const saved = unwrap(response)
        if (saved && saved.id) {
          commit('UPDATE_CATEGORY', saved)
          commit('UPDATE_CURRENT_CATEGORY', saved)
        }
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },
    async deleteCategory({ commit }, id) {
      try {
        await deleteCategory(id)
        commit('DELETE_CATEGORY', id)
        return Promise.resolve()
      } catch (error) {
        return Promise.reject(error)
      }
    },
    async deleteCourse({ commit }, id) {
      try {
        await deleteCourse(id)
        commit('DELETE_COURSE', id)
        return Promise.resolve()
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
    async fetchGroup2learnings({ commit }, data) {
      try {
        const response = await fetchGroup2learnings(data)
        commit('SET_GROUP_2_LEARNINGS', asArray(unwrap(response)))
        return Promise.resolve(response)
      } catch (error) {
        console.log('error console', error)
        return Promise.reject(error)
      }
    },
    async deleteGroup2learnings({ commit }, id) {
      try {
        await deleteGroup2learnings(id)
        commit('DELETE_GROUP_2_LEARNINGS', id)
        return Promise.resolve()
      } catch (error) {
        return Promise.reject(error)
      }
    },
  },
  getters: {
    aircrafts(state) {
      return state.aircrafts.map(course => {
        return {
          id: aircraft.id,
          title: aircraft.title,
          path: aircraft.path,
        }
      })
    },

    courses(state) {
      return state.courses.map(course => {
        return {
          id: course.id,
          title: course.title,
          short_description: course.short_description,
          long_description: course.long_description,
          //group: course.group?course.group.groupname:'',
          path: course.path,
          aircraft_id: course.aircraft_id,
          visible: course.visible,
          //permissions: user.permissions,
          // name: `${user.last_name} ${user.first_name} ${user.mid_name}`,        
        }
      })
    },
    categories(state) {
      return state.categories.map(category => {
        return {
          id: category.id,
          title: category.title,
          description: category.description,
          parent_id: category.parent_id,
          //courses:categories.courses,
          //permissions: group.permissions,
          //permissions_id: group.permissions_id
        }
      })
    }


  }
}

export default CourseModule;