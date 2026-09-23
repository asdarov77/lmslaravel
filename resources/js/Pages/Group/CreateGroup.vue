<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">        
        <v-toolbar-title>Добавить группу </v-toolbar-title>        
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field
            label="наименование группы"
            type="text"
            v-model="groupname"
          ></v-text-field>
          <v-text-field
            label="описание группы"
            type="text"
            v-model="groupdescription"
          ></v-text-field>

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
        <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import { mapState, mapGetters } from 'vuex'

export default {
  //props: ["idEdit"],
  // props: 
  // {
  //   idEdit: {
  //     type: Number,
  //     required: true
  //   },
  // }, 
  data() {
    return {
      groupname: '',
      groupdescription: '',
      //group: [], // группа с указанным ID из базы данных
      errors: [],
    }
  },
  computed: {
    ...mapState('User', ['allGroups','users']),    
    ...mapGetters('User', ['users','groups']),
  },

  created() {   
    
    //if(this.idEdit){
      //console.log(this.idEdit, 'id')
      //this.$store.dispatch('User/fetchGroup', this.idEdit)      
     
    //}   
  },

  methods: {
    submitForm: function () {      
      if (!this.errors.length) {
        const formData = {
          groupname: this.groupname,
          groupdescription: this.groupdescription,
          
        };          
        this.$store                
        .dispatch('User/createGroup', {...formData})
        .then(() => { 
        })
        .catch(error => {
          console.error(error)
        })        
        .finally(() => this.$router.back())            
        }


        // if(this.idEdit){        
        // //axios
        // $api        
        //   .put("/api/groups/" + this.idEdit, formData)
        //   .then((response) => {
        //     console.log(response);
        //      this.$router.push("/groups/list");
        //   })
        // }
        // else
        // $api
        // //axios 
        // .post("/api/groups/", formData)
        //   .then((response) => {
        //     console.log(response);
        //      this.$router.push("/groups/list");
        //   })
        //   .catch((error) => {
        //     if (error.response) {
        //       for (const property in error.response.data) {
        //         this.errors.push(
        //           `${property}: ${error.response.data[property]}`
        //         );
        //       }

        //       console.log(JSON.stringify(error.response.data));
        //     } else if (error.message) {
        //       this.errors.push("Something went wrong. Please try again");
        //       console.log(JSON.stringify(error));
        //     }
        //   });
      }
    },
  //},
};
</script>

<style></style>
