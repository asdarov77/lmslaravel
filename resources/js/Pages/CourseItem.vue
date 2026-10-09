<template>
  <v-col cols="12" sm="8" md="4">
    <v-card class="elevation-12 mx-auto" style="width: 1200px">
      <v-toolbar color="primary">
        <v-toolbar-title>{{ $t('courseItem.title', { name: courses.title }) }}</v-toolbar-title>
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
          <v-alert v-if="errors.length" type="error" density="compact" class="mb-4">
            <div v-for="error in errors" :key="error">{{ error }}</div>
          </v-alert>
        </v-form>
      </v-card-text>
    </v-card>
  </v-col>
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
    // Раньше промис не обрабатывался: при несуществующем id API отдаёт 404,
    // отклонение превращалось в unhandled rejection и роняло страницу.
    try {
      await this.$store.dispatch("Course/fetchCourse", this.idEdit)
    } catch (e) {
      console.warn("Курс не найден:", this.idEdit)
    }
  },
  // methods: {
  // },
};
</script>

