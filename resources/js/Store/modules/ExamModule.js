import {
  fetchExams,
  createExam,
  updateExam,
  fetchExam,
  fetchModuleStats,
  fetchCoursesByCategory,
} from '../../api/exam.api'

import { unwrapResponse as unwrap, asArray } from '../../api/envelope'

const ExamModule = {
  namespaced: true,
  state: () => ({
    exams: [],
    exam: null,
    questionCache: {},
    courseList: [],
    modules: [],
    categories: [],
    pagination: {
      page: 1,
      perPage: 15,
      total: 0,
    },
  }),
  mutations: {
    SET_EXAMS(state, exams) {
      state.exams = asArray(exams)
    },
    SET_EXAM(state, exam) {
      state.exam = exam
    },
    SET_COURSE_LIST(state, courses) {
      state.courseList = asArray(courses)
    },
    SET_MODULES(state, modules) {
      state.modules = asArray(modules)
    },
    SET_CATEGORIES(state, categories) {
      state.categories = asArray(categories)
    },
    SET_PAGINATION(state, pagination) {
      state.pagination = pagination
    },
    SET_QUESTION_CACHE(state, { categoryId, data }) {
      state.questionCache[`questions_${categoryId}`] = data
    },
  },
  actions: {
    async fetchExams({ commit, state }, params = {}) {
      try {
        const response = await fetchExams(params)
        const items = asArray(unwrap(response))
        const meta = (response.data && response.data.meta) || {}
        commit('SET_PAGINATION', {
          ...state.pagination,
          total: meta.total || items.length,
        })
        commit('SET_EXAMS', items)
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchExam({ commit }, id) {
      try {
        const response = await fetchExam(id)
        commit('SET_EXAM', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async createExam({ commit }, data) {
      try {
        const response = await createExam(data)
        commit('SET_EXAM', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async updateExam({ commit, state }, { id, data }) {
      try {
        const idx = state.exams.findIndex(e => e.id === id)
        const previous = idx >= 0 ? { ...state.exams[idx] } : null
        // optimistic
        if (idx >= 0) {
          commit('SET_EXAMS', state.exams.map(e => e.id === id ? { ...e, ...data } : e))
        }
        const response = await updateExam(id, data)
        const saved = unwrap(response)
        if (saved && saved.id) {
          commit('SET_EXAMS', state.exams.map(e => e.id === id ? saved : e))
          commit('SET_EXAM', saved)
        }
        return Promise.resolve(response)
      } catch (error) {
        if (previous) {
          commit('SET_EXAMS', state.exams.map(e => e.id === id ? previous : e))
        }
        return Promise.reject(error)
      }
    },

    async fetchModuleStats({ commit }, categoryId) {
      try {
        const response = await fetchModuleStats(categoryId)
        const data = unwrap(response)
        if (data?.categories) {
          const questionMap = {}
          data.categories.forEach(cat => {
            questionMap[cat.id] = cat.questions
          })
          commit('SET_QUESTION_CACHE', { categoryId, data: questionMap })
        }
        if (data?.modules) {
          commit('SET_MODULES', data.modules)
        }
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchCoursesByCategory({ commit }, categoryId) {
      try {
        const response = await fetchCoursesByCategory(categoryId)
        commit('SET_COURSE_LIST', unwrap(response))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },

    async fetchCategories({ commit }) {
      try {
        const response = await fetchCoursesByCategory(null)
        commit('SET_CATEGORIES', asArray(unwrap(response)))
        return Promise.resolve(response)
      } catch (error) {
        return Promise.reject(error)
      }
    },
  },
  getters: {
    exams(state) {
      return state.exams
    },
    exam(state) {
      return state.exam
    },
    courseList(state) {
      return state.courseList.map(course => ({
        id: course.id,
        title: course.title,
      }))
    },
    modules(state) {
      return state.modules.map(mod => ({
        id: mod.id,
        title: mod.title,
      }))
    },
    categories(state) {
      return state.categories.map(cat => ({
        id: cat.id,
        title: cat.title,
      }))
    },
    questionCache(state) {
      return state.questionCache
    },
  },
}

export default ExamModule