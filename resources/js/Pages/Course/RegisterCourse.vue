<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title
          >Добавление курса </v-toolbar-title
        >        
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field            
            label="Title"
            type="text"            
            v-model="title"
          ></v-text-field>
          <v-text-field            
            label="Краткое описание курса"
            type="text"            
            v-model="shortdescription"
          ></v-text-field>
          <v-text-field            
            label="Полное описание курса"
            type="text"            
            v-model="longdescription"
          ></v-text-field>
          <!-- <v-text-field            
            label="Категория курса"
            type="text"            
            v-model="something"
          ></v-text-field> -->
          <v-select            
            label="Категория курса"
            type="text"
            :items="categories"
            v-model="value"
            item-value="id"
            multiple
          ></v-select>

          <v-text-field            
            label="Путь к курсу"
            type="hyperlink"            
            v-model="path"
          ></v-text-field>
          <file-load></file-load>
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
import FileLoad from '../FileLoadSimple.vue' 

export default {  
   components: {
     FileLoad
   },
  // props: ["idEdit"],  
  data() {
    return {
      title: '',
      shortdescription: '',
      longdescription: '',
      path: '',      
      errors: [],      
      value:null,
    };
  },

    async mounted() {

  },
  computed: {
    ...mapState('Course', ['courses','category','totalCourses','course']),    
    ...mapGetters('Course', ['categories','courses']),
  },
  methods: {
    submitForm: function () {
      if (!this.errors.length) {
        const formData = {
          title: this.title,
          short_description: this.shortdescription,
          long_description: this.longdescription,
          path: this.path,
          category_id:this.value,
        };
        this.$store
          .dispatch("Course/createCourse", { ...formData })
          .then(() => {})
          .catch((error) => {
            console.error(error);
          })
          .finally(() => this.$router.back());      
      //   axios
      //     .post("/api/course", formData)

      //     .then((response) => {
      //       console.log(response.data);
      //        this.$router.push("/courses/list");
            
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
       }
    },
  },
};
</script>

<style></style>