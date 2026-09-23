<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title>Редактирование курса </v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field
            label="Полное название курса"
            type="text"
            v-model="course.title"
          ></v-text-field>
          <v-text-field
            label="Краткое название курса"
            type="text"
            v-model="course.short_description"
          ></v-text-field>
          <v-select
            label="Категория курса"
            type="text"
            v-model="course.categories"
            :items="categories"
            item-value="id"
            item-title="title"
            multiple
          ></v-select>
          <v-text-field
            label="Полное описание курса"
            type="text"
            v-model="course.long_description"
          ></v-text-field>
          <v-text-field
            label="Путь курса"
            type="text"
            v-model="course.path"
          ></v-text-field>
          <v-text-field
            label="Путь hash курса"
            type="text"
            v-model="course.path_hash"
          ></v-text-field>
          <v-switch
            v-model="show"
            hide-details
            true-value="да"
            false-value="нет"
            :label="`Показать: ${show}`"
          ></v-switch>
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
      </v-card-actions> </v-card
    >
  </v-flex>
</template>

<script>

import { mapState, mapGetters } from 'vuex'
import FileLoad from "../FileLoadSimple.vue";
//            item-text="title"
//item-value="id"
export default {  
  props: 
  {
    idEdit: {
      type: Number,
      required: true
    },
  }, 
  data() {
    return {      
      errors: [],      
      show: "да",      
    };
  },
  async mounted() {
        
    this.$store.dispatch("Course/fetchCourse", this.idEdit)

  },
  computed: {
    ...mapState('Course', ['courses','category','totalCourses','course']),    
    ...mapGetters('Course', ['categories','courses']),
  },
  methods: {
    submitForm: function () {
      let  isVisible;
      if (this.show === "да") isVisible = true;
      else isVisible = false;      
      if (!this.errors.length) {
        const formData = {
          title: this.course.title,
          short_description: this.course.short_description,
          long_description: this.course.long_description,
          path: this.course.path,
          path_hash: this.course.path_hash,
          category_id: this.course.categories,
          visible: isVisible,
        };
        this.$store
        .dispatch('Course/updateCourse', {id:this.idEdit,data:formData})
        .then(() => { 
        })
        .catch(error => {
          console.error(error)
        })        
        .finally(() => this.$router.back())        
       }
    },
  },
};
</script>

<style></style>