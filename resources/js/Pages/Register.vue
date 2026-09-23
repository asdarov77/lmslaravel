<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>Добавить пользователя</v-toolbar-title>
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
          <v-select
            prepend-icon="person"
            label="Роль"
            type="text"
            :items="['Администратор', 'Инструктор', 'Обучаемый']"
            v-model="role"
            item-value="id"
            item-title="rolename"
            empty-option
          ></v-select>
          <div v-if="role === 'Обучаемый'">
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
          <v-container v-if="errors.length" class="has-text-centered">
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
          <popup
            :alert="alert"
            :alertType="alertType"
            :snackbarText="snackbarText"
            :overlay="alert"
            :alertFalse="alertFalse"
          ></popup>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <!-- <v-btn v-on:click="regForm" color="success">Сохранить</v-btn> -->
        <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import popup from "./Popup.vue";
import ButtonGroup from "../components/ButtonGroup.vue";
import { mapState, mapGetters } from "vuex";
export default {
  components: { popup,ButtonGroup },
  data() {
    return {
      fio: "",
      role: "",
      password: "",
      password_confirmation: "",
      errors: [],
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
    };
  },
  computed: {
    ...mapState("User", ["totalUsers", "allGroups"]),
    ...mapGetters("User", ["groups"]),
  },
  created() {
    this.$store.dispatch("User/fetchGroups");
  },
  methods: {
    alertFalse() {
      this.alert = false;
    },
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
        const formData = {
          fio: this.fio,
          role: this.role,
          group_id: this.id ? this.id : "",
          password: this.password,
          password_confirmation: this.password_confirmation,
        };

        //console.log(formData,"formData");
        this.$store
          .dispatch("User/createUser", formData)
          .then(() => {
            this.alert = true;
            this.snackbarText = "Пользователь успешно создан";
            this.alertType = "success";
          })
          .catch((error) => {
            //console.error(error)
            this.snackbarText = "Ошибка при создании пользователя";
            this.alertType = "error";
          })
          .finally(() => {
            // FIX ME вкючение оверлея и alert-а нивелируется переходом на пред.страницу
            setTimeout(() => this.$router.back(), 3000);
          });
      }
    },
    cancelBtnHead()
    {
      //console.log('cancel');
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
