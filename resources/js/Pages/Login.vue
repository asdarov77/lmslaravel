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
      //console.log('loginForm')
      //axios.defaults.headers.common["Authorization"] = "";      
      //localStorage.removeItem("token");
      this.errors = [];
      
      if (!this.errors.length ) {
        const formData = {
          fio: this.fio,          
          password: this.password,
        };
        
        //await this.$store
        await this.$store
          .dispatch('Auth/login', formData          
          // .dispatch('login', formData      
          )
          .then(() => {    
           // this.$store
          //.dispatch('Ui/login')                 
            
          })
          .catch((error) => {
            //console.error(error)
            //console.log(error.response.status)
            if (error.response.status === 401) {
               this.snackbarText = "неверный пароль";              
               this.alertType = "error";              
            }
            if (error.response.status === 429) {
               this.snackbarText = "Too Many Requests";              
               this.alertType = "error";              
            }
            if (error.response.status === 500) {
               this.snackbarText = "неверный пользователь или ошибка сервера";              
               this.alertType = "error";              
            }
          })
          .finally(() => {
            this.alert = true;
            this.$router.push('/')
          });
      
        // axios.get("/api/sanctum/csrf-cookie").then((response) => {
        //   axios            
        //     .post("/api/login/", formData)
        //     .then((response) => {
        //       //console.log(response, '   response');
        //       //const token = response.data.auth_token
              
        //       // ?? const roles = response.data.roles; // массив ролей пользователей
        //       const permissions = response.data.permissions; 
        //       //console.log(roles, "roles");
        //       const token = response.data.token;
        //       this.$store.commit("setToken", token);
        //       axios.defaults.headers.common["Authorization"] = "Token " + token;
        //       localStorage.setItem("token", token);
              
        //       this.$store.state.user.isAuthenticated = true;
        //       //console.log(this.$store.state.user.isAuthenticated);

        //       //vuex передача в хранилище имени пользователя
        //       //this.$store.state.user.name = this.fio;
        //       //localStorage.setItem("user", this.fio);
              
        //       //this.$store.state.user.roles = roles;
        //       //this.$store.state.user.permissions = permissions;

        //       //localStorage.setItem("user", this.username);
        //       this.$router.push("/my");
        //     })
        //     .catch((error) => {
        //       if (error.response) {
        //         for (const property in error.response.data) {
        //           this.errors.push(
        //             `${property}: ${error.response.data[property]}`
        //           );
        //         }

        //         console.log(JSON.stringify(error.response.data));
        //       } else if (error.message) {
        //         this.errors.push("Something went wrong. Please try again");

        //         console.log(JSON.stringify(error));
        //       }
        //     });
        // });
      }
    },
  },
};

</script>

<style></style>
