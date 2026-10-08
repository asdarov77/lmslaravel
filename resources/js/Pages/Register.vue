<template>
  <v-col cols="12" sm="8" md="4">
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>{{ $t("users.list.create") }}</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="regForm">
          <v-text-field
            prepend-icon="person"
            name="login"
            label="ФИО"
            type="text"
            id="userLogin"
            v-model="fio"
          ></v-text-field>
          <!--
            Роль выбирается только тем, кто имеет право создавать
            пользователей. Раньше список был всегда полным, включая
            публичную саморегистрацию, где любой мог отметить
            «Администратор» (бэкенд такое значение принимал без проверки).
            Теперь бэкенд всё равно понижает роль до «Обучаемого», а форма
            просто не предлагает недоступный выбор.
          -->
          <v-select
            prepend-icon="person"
            :label="$t('users.role')"
            type="text"
            :items="roleOptions"
            v-model="role"
            item-value="id"
            item-title="rolename"
            :hint="!mayAssignRole ? $t('users.roleHintPublic') : null"
            persistent-hint
            empty-option
          ></v-select>
          <div v-if="role === 'Обучаемый' && mayAssignGroup">
            <v-select
              prepend-icon="person"
              label="Группа"
              type="text"
              :items="groups"
              v-model="id"
              item-value="id"
              item-title="groupname"
            ></v-select>
          </div>
          <v-text-field
            id="password"
            prepend-icon="lock"
            name="password"
            label="Пароль"
            v-model="password"
            type="password"
          ></v-text-field>
          <v-text-field
            id="password_confirmation"
            prepend-icon="lock"
            name="password_confirmation"
            label="Повторите пароль"
            v-model="password_confirmation"
            type="password"
          ></v-text-field>
          <v-alert v-if="errors.length" type="error" density="compact" class="mb-4">
            <div v-for="error in errors" :key="error">{{ error }}</div>
          </v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <!-- <v-btn v-on:click="regForm" color="success">Сохранить</v-btn> -->
        <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-col>
</template>

<script>
import ButtonGroup from "../components/ButtonGroup.vue";
import { mapState, mapGetters } from "vuex";
import { toast } from "../composables/useToast";
export default {
  components: { ButtonGroup },
  data() {
    return {
      fio: "",
      role: "",
      password: "",
      password_confirmation: "",
      errors: [],
      overlay: false,
    };
  },
  computed: {
    ...mapState("User", ["totalUsers", "allGroups"]),
    ...mapGetters("User", ["groups"]),
    ...mapGetters("Auth", ["can"]),

    /**
     * Право назначать роль есть у администратора и инструктора.
     * Публичному посетителю остаётся «Обучаемый».
     */
    mayAssignRole() {
      // can(), а не hasPermission(): один новый стиль проверки вместо двух.
      return this.can('users.create');
    },

    /** Группу назначает тот же набор ролей — она определяет учебный план. */
    mayAssignGroup() {
      return this.mayAssignRole;
    },

    roleOptions() {
      return this.mayAssignRole
        ? ["Администратор", "Инструктор", "Обучаемый"]
        : ["Обучаемый"];
    },
  },
  created() {
    // /api/groups закрыт авторизацией, а /reg — публичная страница.
    // Без catch() отклонённый запрос давал unhandled rejection и ронял
    // страницу регистрации. Пользователь всё равно может отправить форму.
    this.$store.dispatch("User/fetchGroups").catch(() => {
      toast.warning(this.$t("register.groupsUnavailable"));
    });
  },
  methods: {
    submitForm () {
      this.errors = [];

      if (this.fio === "") {
        this.errors.push("отсутствует пользователь");
        //return true;
      }
      if (this.password !== this.password_confirmation) {
        this.errors.push("пароли не совпадают");
        //return true;
      }
      if (!this.errors.length) {
        // Роль и группу отправляем только если их можно назначать.
        // Иначе поля ушли бы в «пользу», а бэкенд их всё равно
        // проигнорировал бы — расхождение формы и поведения.
        const formData = {
          fio: this.fio,
          role: this.mayAssignRole ? this.role : "Обучаемый",
          group_id: this.mayAssignGroup && this.id ? this.id : "",
          password: this.password,
          password_confirmation: this.password_confirmation,
        };

        this.$store
          .dispatch("User/createUser", formData)
          .then(() => {
            toast.success(this.$t("register.created"));
          })
          .catch((error) => {
            toast.error(toast.fromError(error, this.$t("register.createFailed")));
          })
          .finally(() => {
            /*
             * Раньше здесь стоял setTimeout(3000): пауза нужна была,
             * чтобы локальный тост успел отрисоваться ДО ухода со
             * страницы. Сообщение переживает навигацию, поэтому ждать
             * нечего, а пользователь три секунды смотрел на пустую
             * форму с сообщением, которого уже не видно.
             */
            this.$router.back();
          });
      }
    },
    cancelBtnHead()
    {
      this.$router.go(-1);
    },
  },
};
</script>

<style>
/* * {
  margin :0;
  padding: 0;
  box-sizing: border-box;
} */
.errors {
  border: 2px solid teal;
}
</style>
