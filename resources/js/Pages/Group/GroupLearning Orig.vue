<template>
  <v-card class="elevation-12 mx-auto" style="width: 600px; overflow: visible">
    <v-toolbar color="primary">
      <v-toolbar-title>Запись групп пользователей на курсы</v-toolbar-title>
    </v-toolbar>

    <v-card-text>
      
      <v-form v-on:@submit.prevent="submitForm">
        <v-select
          label="Класс"
          type="text"
          :items="aircrafts"
          v-model="group.aircrafts"
          item-value="id"
          item-title="path"
          empty-option
          clearable:true          
          @update:modelValue="changeAir(group.aircrafts)"
        ></v-select>
        <v-select
          label="Категории"
          type="text"
          :items="catFilter"
          v-model="group.categories"
          item-value="id"
          item-title="title"
          empty-option
          
          @update:modelValue="changeCat(group.categories)"
          ref="selectedElcat"
        ></v-select>
        <v-divider></v-divider>
        <v-select
          label="Курсы"
          type="text"
          :items="courseFilter"
          v-model="group.courses"
          multiple
          item-value="id"
          item-title="title"
          empty-option          
          ref="selectedElcourse"            
        >
        <v-select
          label="TEst"
          type="text"
          :items="['A', 'B', 'C']"
          >
        </v-select>
      </v-select>

        <v-select
          label="Инструктор"
          type="text"
          :items="filteredUserRole"
          v-model="group.teacher"                    
          item-title="fio"
          
        ></v-select>
        <v-select
          label="Вид занятия"
          type="text"
          :items="['Лекция', 'Практическое занятие', 'Самостоятельная подготовка']"
          v-model="group.typeOfLesson"          
          item-value="id"          
                             
        ></v-select>
        
        <v-divider></v-divider>
        <v-container>
          <v-row>
            <v-col>
              <v-text-field
                type="date"
                v-model="group.study_from"
                label="начало"
                variant="outlined"
              >
              </v-text-field>
            </v-col>

            <v-col>
              <v-text-field
                type="date"
                v-model="group.study_to"
                label="конец"
                variant="outlined"
              >
              </v-text-field>
            </v-col>
          </v-row>
        </v-container>

        <v-container class="notification is-danger" v-if="errors.length">
          
          <p v-for="error in errors" v-bind:key="error" class="has-text-centered">
            <!-- {{ error }} -->
          </p>
        </v-container>
        <popup
            :alert="alert"
            :alertType="alertType"
            :snackbarText="snackbarText"
            :overlay="alert"
            :alertFalse="alertFalse"
          ></popup>
      </v-form>
    </v-card-text>
    <v-card-actions>
      <v-spacer></v-spacer>
      <!-- <v-btn v-on:click="login" color="primary" to="/MyAccount">Login</v-btn> -->
      <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn>
    </v-card-actions>
  </v-card>
</template>

<script>
//  <!-- @update:modelValue="changeCourse(group.courses)" -->
import PermissionWrapper from "../PermissionWrapper.vue";
import popup from "../Popup.vue";
import { mapState, mapGetters } from "vuex";
import $api from "../../api/httpClient";
import ButtonGroup from "../../components/ButtonGroup.vue";
import Treeselect from 'vue3-treeselect';
import 'vue3-treeselect/dist/vue3-treeselect.css';

export default {
  components: { PermissionWrapper, popup, ButtonGroup, Treeselect },
  props: ["idEdit"],
  data() {
    return {
      errors: [],
      isLoading: false,
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
//----------- отфильтрованные массивы для item в v-select--------------
      airFilter: [],
      catFilter: [],
      courseFilter: [],
      userFilter:[],
      
      
// -------------------------конец отфильтрованные массивы --------------
      // dateFrom: group.dateFrom,
      // dateTo: group.dateTo,
    };
  },
  created() {
    // this.$store
    //   .dispatch("User/fetchUsers")
    //   .catch((error) => console.error(error))
    //   .finally(() => (this.isLoading = false));

    this.$store.dispatch("User/fetchUsers");

    this.$store.dispatch("Course/fetchCategories");
    this.$store.dispatch("Course/fetchCourses");
    this.$store.dispatch("Course/fetchAircrafts");
    //this.$store.dispatch("Course/fetchCoursesFilter", );
    this.$store.dispatch("User/fetchGroup", this.idEdit);
  },
  computed: {
    ...mapState("User", ["group","users"]),
    ...mapState("Course", ["courses", "categories", "aircrafts"]),

    ...mapGetters("Course", ["courses"]),
    // ...mapGetters("Auth", ["hasPermission"]),

    hasEditPermission() {
      return this.hasPermission(["manage-users"], "Manage users");
    },

    filteredUserRole() {      
      return this.users.filter(item => item.role === 'Инструктор')
    },
  },
  watch: {
    dateFrom(value, oldValue) {
      //console.log(oldValue,"old Value");
      //console.log(value,"new Value");
    },
    dateTo(value, oldValue) {
      // console.log(oldValue,"old Value");
      // console.log(value,"new Value");
    },
  },

  methods: {
    alertFalse() {
      this.alert = false;
    },
    cancelBtn() {
      this.$emit("cancelBtn");
    },
    alertFalse() {
      this.alert = false;
    },
    changeAir(id_air) {
      this.$refs["selectedElcourse"].reset();
      this.$refs["selectedElcat"].reset();
      this.courseFilter = [];
      this.catFilter = [];
      this.catFilter = this.categories.filter(function (e) {
        return e.aircraft_id === id_air;
      });
    },
    changeCat(id_cat) {
      this.$refs["selectedElcourse"].reset();
      this.courseFilter = []; 
      if(id_cat)
      {
      let urlToGet =
        "/api/course/?" +
        "aircraft_id=" +
        this.group.aircrafts +
        "&category_id=" +
        this.group.categories;
      $api.get(urlToGet).then((response) => {
        this.courseFilter = response.data;
      });
    }
    },

    submitForm() {
      this.errors = [];
      if(this.group.study_from > this.group.study_to) {
        this.errors.push("дата начала не может быть раньше даты окончания");
        this.snackbarText = "дата начала не может быть раньше даты окончания";
        this.alertType = "error";          
        this.alert = true;          
      }
      if(!this.group.study_from) {
        this.errors.push("дата начала не задана");
        this.snackbarText = "дата начала не задана";
        this.alertType = "error";
        this.alert = true;      
      }
      if(!this.group.study_to) {
        this.errors.push("дата окончания не задана");
        this.snackbarText = "дата окончания не задана";
        this.alertType = "error";
        this.alert = true;      
      }
      if (!this.errors.length && (this.group.study_from && this.group.study_to && this.group.study_from < this.group.study_to)) {
        const formData = {
          course_id: this.group.courses,
          group_id: this.idEdit,
          category_id: this.group.categories,          
          study_from: this.group.study_from,
          study_to: this.group.study_to,
          teacher: this.group.teacher,
          typeOfLesson: this.group.typeOfLesson,
        };        
        //console.log(formData);
        
        //let urlToUp = "/api/group/learning/";
        this.$store.dispatch("Course/fetchGroup2learnings", formData)
        .catch((error) => console.error(error))
        .finally(() =>  this.$router.push("/groups/list"));
        // $api          
        //   .post(urlToUp, formData)
        //   .then((response) => {
        //     //console.log(response, 'resp');
        //     this.$router.push("/groups/list");
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
        //   })
        //   .finally(() => {
        //     this.isLoading = false          
        //   });
       }
     },
  },
};
</script>