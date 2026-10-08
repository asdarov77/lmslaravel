<template>
  <v-col cols="12" sm="8" md="4">
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>{{ $t("login.formTitle") }}</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="loginForm">
          <v-text-field
            prepend-icon="person"
            name="login"
            label="ФИО"
            type="text"
            id="userLogin"
            v-model="fio"
          ></v-text-field>
          <v-text-field
            id="password"
            prepend-icon="lock"
            name="password"
            label="Пароль"
            v-model="password"
            type="password"
          ></v-text-field>
          <!-- <LanguageSelector /> -->
          <v-alert v-if="errors.length" type="error" density="compact" class="mb-4">
            <div v-for="error in errors" :key="error">{{ error }}</div>
          </v-alert>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn v-on:click="loginForm" color="primary">{{ $t("login.submitBtn") }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-col>
</template>

<script>
import LanguageSelector from '../components/LanguageSelector.vue'
import { toast } from '../composables/useToast'
//import { LanguageService } from '../services/language.service'
//import * as storage from "../Store/index.js";
export default {
  // name: "LoginComponent",
    components: {
    LanguageSelector
  },
  data() {    
    return {
      fio: "",      
      password: "",
      password_confirmation: [],
      errors: [],

      alert: false,
        overlay: false,      
    }
  },
  beforeDestroy() {
    // язык интерфейса    
    // LanguageService.saveLanguage('ru')    
    // this.$store.commit('Ui/SET_LANGUAGE', 'ru')
  },
  methods: {
     async loginForm() {
      this.errors = [];
      
      const formData = {
        fio: this.fio,          
        password: this.password,
      };
      
      await this.$store
        .dispatch('Auth/login', formData)
        .then(() => {    
          toast.success(this.$t("login.success"));
          // После входа ведём в кабинет, а не на '/': корневой адрес
          // теперь редиректит на /dashboard, и лишний редирект только
          // показывал бы в адресной строке '/', а не '/dashboard'.
          this.$router.push('/dashboard')
        })
        .catch((error) => {
          console.error(error)
          /*
           * Разбор по коду ответа, а не одно общее сообщение: «ошибка
           * сети» и «неверный пароль» требуют от пользователя разных
           * действий, и раньше он получал одну строку на все случаи.
           */
          const status = error?.response?.status;

          if (!error?.response) {
            toast.error(this.$t("login.networkError"));
          } else if (status === 401) {
            toast.error(this.$t("login.wrongPassword"));
          } else if (status === 429) {
            toast.error(this.$t("login.tooManyRequests"));
          } else if (status >= 500) {
            toast.error(this.$t("login.serverError"));
          } else {
            toast.error(toast.fromError(error, this.$t("login.unknownError")));
          }
        });
    },
  },
};

</script>

<style></style>
