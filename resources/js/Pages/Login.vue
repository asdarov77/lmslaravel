<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title>Форма авторизации</v-toolbar-title>
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
          <v-container class="notification is-danger" v-if="errors.length">
            <!--class="has-text-centered"> -->
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn v-on:click="loginForm" color="primary">Вход</v-btn>
      </v-card-actions>
    </v-card>
    <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert" :alertFalse="alertFalse"></popup>  
  </v-flex>
</template>

<script>
import LanguageSelector from '../components/LanguageSelector.vue'
//import { LanguageService } from '../services/language.service'
import popup from './Popup.vue';
//import * as storage from "../Store/index.js";
export default {
  // name: "LoginComponent",
    components: {
    LanguageSelector, popup
  },
  data() {    
    return {
      fio: "",      
      password: "",
      password_confirmation: [],
      errors: [],

      alert: false,
        alertType: "",
        overlay: false,      
        snackbarText: "", 
    }
  },
  beforeDestroy() {
    // язык интерфейса    
    // LanguageService.saveLanguage('ru')    
    // this.$store.commit('Ui/SET_LANGUAGE', 'ru')
  },
  methods: {
    alertFalse() {
      this.alert = false
    },
     async loginForm() {
      this.errors = [];
      
      const formData = {
        fio: this.fio,          
        password: this.password,
      };
      
      await this.$store
        .dispatch('Auth/login', formData)
        .then(() => {    
          this.alert = true;
          this.snackbarText = "Успешный вход";
          this.alertType = "success";
          this.$router.push('/')
        })
        .catch((error) => {
          console.error(error)
          // Безопасная обработка ошибок (защита от CORS и network ошибок)
          if (!error.response) {
            this.snackbarText = "Ошибка сети или CORS. Проверьте подключение к серверу.";
            this.alertType = "error";
          } else if (error.response.status === 401) {
             this.snackbarText = "Неверный пароль";              
             this.alertType = "error";              
          } else if (error.response.status === 429) {
             this.snackbarText = "Слишком много запросов. Попробуйте позже.";              
             this.alertType = "error";              
          } else if (error.response.status === 500) {
             this.snackbarText = "Ошибка сервера";              
             this.alertType = "error";              
          } else {
             this.snackbarText = "Произошла ошибка: " + (error.message || "Неизвестная ошибка");
             this.alertType = "error";
          }
        })
        .finally(() => {
          this.alert = true;
        });
    },
  },
};

</script>

<style></style>
