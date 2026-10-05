<template>
    <component :is="viewComponent" v-if="viewComponent"></component>
    <!--
        Fallback для неизвестной/пустой роли. Раньше здесь был
        `<component :is="null">`, который рендерил пустой экран без
        объяснений: roleComponentMapping искал ключ по строке
        user.role, а роль, назначенная через chroll, в этой колонке
        отсутствует. Теперь роль нормализуется через Auth/hasRole,
        а MyAccount доступен любому вошедшему и служит безопасной
        заглушкой.
    -->
    <v-alert v-else type="warning" text-align="center" class="u-alert--page">
        {{ $t("common.roleUnknown") }}
        <template #append>
            <v-btn text color="primary" to="/">{{ $t("common.goHome") }}</v-btn>
        </template>
    </v-alert>
</template>

<script>
import UserPage from "./User/UserPage.vue";
import MyAccount from "./MyAccount.vue";
import { mapGetters } from "vuex";

export default {
    components: { UserPage, MyAccount },
    computed: {
        ...mapGetters("Auth", ["hasRole", "roleSlugs", "loggedIn"]),
        /**
         * Компонент по роли. Ключи — канонические slug'и, как в
         * User::ROLE_ALIASES, а не русские названия: иначе проверка
         * расходилась с бэкендовой.
         */
        viewComponent() {
            if (!this.loggedIn) return null;
            if (this.hasRole("trainee")) return UserPage;
            if (this.hasRole("admin", "instructor")) return MyAccount;

            // Неизвестная роль: показываем предупреждение и ссылку,
            // вместо пустого экрана.
            console.warn("[Home] Неизвестная роль пользователя:", this.roleSlugs);
            return null;
        },
    },
};
</script>
