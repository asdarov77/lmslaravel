import UserModule from '../../resources/js/Store/modules/UserModule'

describe('UserModule', () => {
  it('optimistically updates user and can rollback', () => {
    const state = UserModule.state()
    state.users = [{ id: 5, fio: 'Old' }]
    const prev = { ...state.users[0] }
    UserModule.mutations.UPDATE_USER(state, { id: 5, fio: 'New' })
    expect(state.users[0].fio).toBe('New')
    UserModule.mutations.UPDATE_USER(state, prev)
    expect(state.users[0].fio).toBe('Old')
  })
})


