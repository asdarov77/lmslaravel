<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto" style="width: 600px">
      <v-toolbar color="primary">
        <v-toolbar-title
          >Назначить роль пользователя {{ usernameEdit }}</v-toolbar-title
        >
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-select
            label="Роль"
            type="text"
            :items="roles"
            v-model="user.roles"
            multiple
            item-value="id"
            item-title="rolename"
            empty-option
          ></v-select>

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
        <!-- <v-btn v-on:click="login" color="primary" to="/MyAccount">Login</v-btn> -->
        <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import $api from "../../api/httpClient";

import { mapState } from "vuex";
export default {

  
  props: ["idEdit"],
  data() {
    return {
      user: [], // пользователь с указанным ID из базы данных
      errors: [],
      roles: [], // Роли      
      value: [],      
    };
  },

  created() {
    //console.log('mounted');
    //axios
    $api

    .get("api/user/list/" + this.idEdit).then((response) => {
      //console.log(response.data, "пользователь");
      this.user = response.data;
    });

    $api
    .get("api/role").then((resp) => {
      this.roles = resp.data;
    });

  },  
  methods: {
     submitForm: function () {
      
      if (!this.errors.length) {
        
        const formData = {
          
          role_id: this.user.roles,
          
        };

        let urlToUp = "/api/user/chroll/" + this.idEdit;                       
        //axios
        $api
          //.put(urlToUp, formData)
          .put(urlToUp, formData)

          .then((response) => {
            //console.log(response);

            this.$router.push("/user/list");
          })
          .catch((error) => {
            if (error.response) {
              for (const property in error.response.data) {
                this.errors.push(
                  `${property}: ${error.response.data[property]}`
                );
              }

              //console.log(JSON.stringify(error.response.data));
            } else if (error.message) {
              this.errors.push("Something went wrong. Please try again");
              //console.log(JSON.stringify(error));
            }
          });
      }
    },
  },
};
</script>

<style src="@vueform/multiselect/themes/default.css"></style>

