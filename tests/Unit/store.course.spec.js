import CourseModule from '../../resources/js/Store/modules/CourseModule'

describe('CourseModule', () => {
  it('sets pagination and courses from response', () => {
    const state = CourseModule.state()
    const items = [{ id: 1, title: 'A' }]
    const pagination = { page: 2, perPage: 15, total: 1, totalPages: 1 }
    CourseModule.mutations.SET_COURSES(state, items)
    CourseModule.mutations.SET_PAGINATION(state, pagination)
    expect(state.courses).toEqual(items)
    expect(state.pagination).toEqual(pagination)
  })
})


