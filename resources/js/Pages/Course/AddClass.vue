
<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title>Добавление класса</v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form @submit.prevent="submitForm">
          <v-combobox v-model="path" :items="tags" label="Выберите класс для добавления" :disabled="loading"></v-combobox>
          <v-text-field :disabled="loading" label="Описание" type="text" v-model="title"></v-text-field>

          <div>
            <v-progress-linear v-if="loading" :value="progress" height="5" :indeterminate="true"
              :color="progressColor"></v-progress-linear>
          </div>
          <!-- компонент FileUploader для загрузки и распаковки. пока отключаем его -->
          <!-- <div>
            <file-uploader></file-uploader>
            <v-progress-linear v-if="progress !== null" :value="progress" :height="10"
              color="primary"></v-progress-linear>
          </div> -->
        </v-form>
      </v-card-text>
      <v-card-actions class="d-flex justify-space-between">
        <v-spacer></v-spacer>
        <v-btn @click="clearDatabase" v-if="!loading" class="mr-auto d-flex" color="error">
          Очиститьбазу данных
        </v-btn>
        <ButtonGroup class="mr-3" v-if="!loading" @submitForm="uploadData" @cancelBtn="cancelBtnHead"></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import FileUploader from '../../components/FileUploader.vue'
import { mapState, mapGetters } from "vuex";
import ButtonGroup from "../../components/ButtonGroup.vue";
import $api from "../../api/httpClient";
import 'vuetify/dist/vuetify.min.css';
import ProgressLinear from '../../components/ProgressLinear.vue';

const apiUrl = import.meta.env.VITE_APP_URL;

export default {
  components: {
    ButtonGroup,
    ProgressLinear,
    FileUploader,
  },
  data() {
    return {
      allTags: [],
      tags: [],
      auks: [],
      errors: [],
      title: "",
      path: "",
      loading: false,
      progress: 0,
      progressColor: 'blue',
    };
  },



  async mounted() {


    try {
      const response = await $api.get(apiUrl + "/api/classesfs");
      this.allTags = response.data;
      this.tags = this.allTags;
    } catch (error) {
      console.error(error);
    }
  },

  computed: {
    ...mapState("Course", ["courses", "category", "totalCourses", "course"]),
    ...mapGetters("Course", ["categories", "courses"]),
  },

  methods: {
    async uploadData() {
      // if (!(this.path && this.title)) {
      //   return;
      // }
      const data = {
        title: this.title,
        path: this.path,
      };
      this.loading = true;
      try {
        const response = await $api.post(apiUrl + "/api/classes", data, {
          onUploadProgress: (progressEvent) => {
            this.progress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
          },
        });
        this.tags = this.tags.filter((tag) => tag !== this.path);
        this.auks = response.data.auks;
      } catch (error) {
        console.error(error);
      } finally {
        this.loading = false;
        this.progress = 0;
      }
    },

    async clearDatabase() {
      try {
        const response = await $api.post(apiUrl + "/api/clear-database");
        console.log(response.data);
        this.tags = this.allTags
      } catch (error) {
        console.error(error);
      }
    },

    submitForm() {
      if (!this.path || !this.title) {
        return;
      }
      const formData = {
        title: this.title,
        path: this.path,
      };
      $api.post(apiUrl + "/api/classes", formData)
        .then((response) => {
          this.auks = response.data.auks;
        })
        .finally(() => {
          this.$router.go(-1);
        });
    },

    cancelBtnHead() {
      this.$router.go(-1);
    },

    getFilteredTags(search) {
      if (!search) {
        return this.allTags;
      }
      const regex = new RegExp(search.trim(), "i");
      return this.allTags.filter((tag) => regex.test(tag));
    },
  },

  watch: {
    path(search) {
      this.filteredTags = this.getFilteredTags(search);
    },
  },

  computed: {
    filteredTags() {
      return this.getFilteredTags(this.path);
    },
  },
};
</script> 

