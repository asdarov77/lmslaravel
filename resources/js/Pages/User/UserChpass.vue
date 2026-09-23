<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>Сменить пароль {{ usernameEdit }}</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
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
          <popup
            :alert="alert"
            :alertType="alertType"
            :snackbarText="snackbarText"
            :overlay="alert"
            :alertFalse="alertFalse"
          ></popup>
          <v-container class="notification is-danger" v-if="errors.length">
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import popup from "../Popup.vue";
import { mapState, mapGetters } from "vuex";
export default {
  components: {
    popup,
  },
  props: ["idEdit"],
  data() {
    return {
      password: "", // пользователь с указанным ID из базы данных
      password_confirmation: "",
      errors: [],

      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
    };
  },
  mounted() {
    //console.log(this.idEdit, "idEdit")
  },
  computed: {
    ...mapState("User", ["user"]),
    //...mapGetters('User', ['users','groups']),
  },
  methods: {
    alertFalse() {
      this.alert = false;
    },
    submitForm: function () {
      if (this.password !== this.password_confirmation) {
        this.errors.push("пароли не совпадают");
        this.alert = true;
        this.snackbarText = "пароли не совпадают";
        this.alertType = "error";
        setTimeout(() => this.$router.back(), 3000);
      }
      if (!this.errors.length) {
        const formData = {
          password: this.password,
        };

        this.$store
          .dispatch("User/chpassUser", { id: this.idEdit, data: formData })
          .then(() => {
            this.snackbarText = "пароль успешно изменен";
            this.alertType = "success";
          })
          .catch((error) => {
            console.error(error);
            this.snackbarText = "ошибка смены пароля";
            this.alertType = "error";
          })
          .finally(() => {
            this.alert = true;
            setTimeout(() => this.$router.back(), 3000);
          });
      }
    },
  },
};
</script>
