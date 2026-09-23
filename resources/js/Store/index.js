import { createStore } from 'vuex';
import AuthModule from "./modules/AuthModule";
import UserModule from "./modules/UserModule";
import CourseModule from "./modules/CourseModule";
import UiModule from "./modules/UiModule";
import UserPageModule from "./modules/userPage.store";


const store = createStore({
    modules: {
        Auth: AuthModule,
        User: UserModule,
        Course: CourseModule,
        Ui: UiModule,
        UserPage:UserPageModule
    }
})

export default store;