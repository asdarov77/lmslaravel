<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto" style="width: 1200px">
      <v-toolbar color="primary">
        <v-toolbar-title>Описание курса {{ courses.title }} </v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form>
          <v-text-field            
            label="описание курса"
            type="text"
            v-model="course.title"
          ></v-text-field>
          <v-text-field            
            label="короткое описание"
            type="text"
            v-model="course.short_description"
          ></v-text-field>
          <v-text-field            
            label="полное описание"
            v-model="course.long_description"
            type="text"
          ></v-text-field>
            <v-select
            label="категории"
            type="text"            
            v-model="course.categories"
            multiple
            empty-option
            hide-no-data
            hide-selected
          ></v-select>    
          <v-container class="notification is-danger" v-if="errors.length">
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
        </v-form>
      </v-card-text>
    </v-card>
  </v-flex>
</template>

<script>

import { mapState, mapGetters } from 'vuex'
export default {

  props: {
    idEdit: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      errors: [],                 
    };
  },
  computed: {
    ...mapState('Course', ['courses','category','totalCourses','course']),    
    ...mapGetters('Course', ['categories','courses']),
  },
  async mounted() {
    //console.log("mounted",this.idEdit, "этот курс");    
    this.$store.dispatch("Course/fetchCourse", this.idEdit)    
  },
  // methods: {
  // },
};
</script>

