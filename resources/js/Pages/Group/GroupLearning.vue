<template>
  <v-card class="elevation-12 mx-auto" style="width: 600px; overflow: visible">
    <v-toolbar color="primary">
      <v-toolbar-title>Запись групп пользователей на курсы</v-toolbar-title>
    </v-toolbar>
    <!-- {{ group.courses }} -->
    <!-- {{ options }} -->
    <!-- {{ courseFilter }} -->
    <!-- {{ selectedOptions }} -->
    <!-- {{ courseFilter }} -->
    <v-card-text>

      <v-form v-on:@submit.prevent="submitForm">
        <!-- ***************удалить*************** -->
        <!-- <treeselect placeholder="тест" 
        v-model="group.courses" 
        :multiple="true" 
        :options="options"                  
         /> -->
        <!-- ***************удалить*************** -->
        <v-select label="Класс" type="text" :items="aircrafts" v-model="group.aircrafts" item-value="id" item-title="path"
          empty-option clearable:true @update:modelValue="changeAir(group.aircrafts)">
        </v-select>
        <v-select label="Категории" type="text" :items="catFilter" v-model="group.categories" item-value="id"
          item-title="title" empty-option @update:modelValue="changeCat(group.categories)" ref="selectedElcat">
        </v-select>
        <v-divider></v-divider>
        <!-- <v-select
          label="Курсы"
          type="text"
          :items="courseFilter"
          v-model="group.courses"
          multiple
          item-value="id"
          item-title="title"
          empty-option          
          ref="selectedElcourse"            
        @onClick:modelValue="onClick" // вернуть в treeselect
        @update:modelValue="changeCourseTree(group.courses)"  
        >        
      </v-select> -->
        <treeselect placeholder="Курсы" :default-expand-level="1" v-model="group.courses" :multiple="true"
          :options="options" @update:modelValue="test(group.courses)" ref="treeSelectInput" />

        <!-- <v-progress-linear ></v-progress-linear> -->


        <v-select label="Инструктор" type="text" :items="filteredUserRole" v-model="group.teacher"
          item-title="fio"></v-select>
        <v-select label="Вид занятия" type="text"
          :items="['Лекция', 'Практическое занятие', 'Самостоятельная подготовка']" v-model="group.typeOfLesson"
          item-value="id"></v-select>

        <v-divider></v-divider>
        <v-container>
          <v-row>
            <v-col>
              <v-text-field type="date" v-model="group.study_from" label="начало" variant="outlined">
              </v-text-field>
            </v-col>

            <v-col>
              <v-text-field type="date" v-model="group.study_to" label="конец" variant="outlined">
              </v-text-field>
            </v-col>
          </v-row>
        </v-container>

        <v-container class="notification is-danger" v-if="errors.length">

          <p v-for="error in errors" v-bind:key="error" class="has-text-centered">
            <!-- {{ error }} -->
          </p>
        </v-container>
        <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert"
          :alertFalse="alertFalse"></popup>
      </v-form>
    </v-card-text>
    <v-card-actions>
      <v-spacer></v-spacer>
      <!-- <v-btn v-on:click="login" color="primary" to="/MyAccount">Login</v-btn> -->
      <!-- <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn> -->
      <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
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
      userFilter: [],


      // -------------------------конец отфильтрованные массивы --------------
      //------------------------------options for treeview----------------------------
      options: [],
      selectedOptions: [] //здесь выбранные пункты куска дерева

      // ***************удалить*************** -->
      // options: [ {
      //     id: 'a',
      //     label: 'a',
      //     children: [ {
      //       id: 'aa',
      //       label: 'aa',
      //       children: [ {
      //       id: 'aaa',
      //       label: 'aaa',
      //     }, {
      //       id: 'abb',
      //       label: 'abb',
      //     } ],
      //     }, {
      //       id: 'ab',
      //       label: 'ab',
      //     } ],
      //   }, {
      //     id: 'b',
      //     label: 'b',
      //   }, {
      //     id: 'c',
      //     label: 'c',
      //   } ]
      // ***************удалить*************** -->

      //---------------------------------удалить потом----------------------------
      // dateFrom: group.dateFrom,
      // dateTo: group.dateTo,
    };
  },
  created() {
    // set default value for study_from to today's date
    let today = new Date()
    this.group.study_from = today.toISOString().substr(0, 10)
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
    ...mapState("User", ["group", "users"]),
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
      // this.$refs["selectedElcourse"].reset();
      //console.log(this.$refs["treeSelectInput"], "treeselect");
      this.$refs["treeSelectInput"].clear();
      this.$refs["selectedElcat"].reset();
      this.courseFilter = [];
      this.catFilter = [];
      this.catFilter = this.categories.filter(function (e) {
        return e.aircraft_id === id_air;
      });
    },
    changeCat(id_cat) {
      this.$refs["treeSelectInput"].clear();
      this.courseFilter = [];
      if (id_cat) {
        let urlToGet =
          "/api/course/?" +
          "aircraft_id=" +
          this.group.aircrafts +
          "&category_id=" +
          this.group.categories;
        $api.get(urlToGet).then((response) => {
          this.courseFilter = response.data;

          //console.log(urlToGet);
          //console.log(this.courseFilter[0].aukstructures);
          // замена aukstructure плоского на иерархическую
          var index;
          for (index = 0; index < this.courseFilter.length; ++index) {
            // console.log(this.courseFilter[0].aukstructures, '\n\n\n\n\n\n\n'); 
            // console.log(this.courseFilter[index].aukstructures, '\n\n\n\n\n\n\n'); 
            this.courseFilter[index].aukstructures = this.flatToHierarchy(this.courseFilter[index].aukstructures);
          }
          //console.log(this.courseFilter[0].aukstructures, "this.courseFilter")     
          // копия массива courseFilter c переименованными полями для работы с treeview
          //console.log(this.courseFilter[0].aukstructures)
          //           this.options = this.courseFilter.aukstructures.map(function(aukstructure) {
          //   return aukstructure;
          // });
          //console.log(this.options)
          //for (ii = 0; index < this.courseFilter.length; ++ii) {

          // this.options = this.courseFilter.map(item => ({
          //           id: item.id,
          //           label: item.title,

          //           // parent_id: item.parent_id,
          //           // children: item.aukstructures.map(a => ({
          //           //    id: a.id,            
          //           //    label: a.title,            
          //           //   //  parent_id: a.parent_id,

          //           //    children: a.children.map(aa => ({
          //           //    id: aa.id,            
          //           //    label: aa.title,            
          //           //   //  parent_id: aa.parent_id,

          //           //   children: aa.children.map(aaa => ({
          //           //    id: aaa.id,            
          //           //    label: aaa.title,                         
          //           //   //  parent_id: aaa.parent_id,
          //           //  }))   
          //           //  }))                 
          //          //}))     
          //         }));

          // исправленный вариант,где не дублируется название курса
          //console.log(JSON.stringify(this.courseFilter[0]))
          // this.options = this.courseFilter.map(course =>
          //   course.aukstructures.map(item => ({
          //     id: item.id,
          //     label: item.title,
          //      children: item.children.map(a => ({
          //        id: a.id,
          //        label: a.title,
          //       children: a.children.map(aa => ({
          //         id: aa.id,
          //         label: aa.title,
          //         children: aa.children.map(aaa => ({
          //           id: aaa.id,
          //           label: aaa.title,
          //         }))
          //       }))
          //      }))
          //   }))
          // ).flat();







          this.options = this.courseFilter.map(course =>
            course.aukstructures.map(item =>
              this.renameFieldsRecursive(item, 'title', 'label')
            )
          ).flat();


          // console.log(this.options);

          //this.options = this.courseFilter.map(item => ({
          //           id: item.id,
          //           label: item.title,
          // aukstructures: item.map(a => ({
          //           // parent_id: item.parent_id,
          //           // children: item.aukstructures.map(a => ({
          //  id: a.id,            
          //  label: a.title,            
          //           //   //  parent_id: a.parent_id,

          //           //    children: a.children.map(aa => ({
          //           //    id: aa.id,            
          //           //    label: aa.title,            
          //           //   //  parent_id: aa.parent_id,

          //           //   children: aa.children.map(aaa => ({
          //           //    id: aaa.id,            
          //           //    label: aaa.title,                         
          //           //   //  parent_id: aaa.parent_id,
          //           //  }))   
          //           //  }))                 
          //   }))     
          //  }));





          //console.log(this.options)      
        });
      }
    },

    // Метод для переименования полей в массиве options
    // этот массив явлется входными данными в treeselect
    renameFieldsRecursive(obj, oldName, newName) {
      if (typeof obj !== 'object' || obj === null) {
        return obj;
      }

      const newObj = {};
      for (const [key, value] of Object.entries(obj)) {
        if (key === oldName) {
          newObj[newName] = value;
        } else if (key === 'children') {
          newObj.children = value.map(child => this.renameFieldsRecursive(child, oldName, newName));
        } else {
          newObj[key] = this.renameFieldsRecursive(value, oldName, newName);
        }
      }

      return newObj;
    },


    // метод преобразования структуры плоской в иерархическую

    flatToHierarchy(flat) {
      const roots = [],
        map = [],
        id = [];
      flat.forEach(item => {
        map.push(Object.assign({}, item)); // копируем
        id.push(item.id);
      });
      let i;
      map.forEach(item => {
        // Не пойму зачем вы вставили сюда null !?
        if ( /*item.parent_id === null ||*/ !item.parent_id || (i = id.indexOf(item.parent_id)) === -1) {
          roots.push(item);
          return;
        }
        if (map[i].children) {
          map[i].children.push(item);
        }
        else {
          map[i].children = [item];
        }
      });
      return roots;
    },
    // проверка текущей даты
    validateDate() {
      let today = new Date()
      let studyFrom = new Date(this.group.study_from)
      if (studyFrom < today) {
        this.errors.push('Дата начала не может быть меньше текущей даты')
        return false
      }
      return true
    },



    submitForm() {
      this.errors = [];
      if (this.group.study_from > this.group.study_to) {
        this.errors.push("дата начала не может быть раньше даты окончания");
        this.snackbarText = "дата начала не может быть раньше даты окончания";
        this.alertType = "error";
        this.alert = true;
      }
      if (!this.group.study_from) {
        this.errors.push("дата начала не задана");
        this.snackbarText = "дата начала не задана";
        this.alertType = "error";
        this.alert = true;
      }
      if (!this.group.study_to) {
        this.errors.push("дата окончания не задана");
        this.snackbarText = "дата окончания не задана";
        this.alertType = "error";
        this.alert = true;
      }
      if (!this.validateDate()) {
        this.errors.push("дата начала не может быть меньше текущей");
        this.snackbarText = "дата начала меньше текущей";
        this.alertType = "error";
        this.alert = true;
      }
      console.log("this.validate()")
      if (!this.errors.length && (this.group.study_from && this.group.study_to && this.group.study_from < this.group.study_to)) {
        const formData = {
          course_id: this.group.courses,
          group_id: this.idEdit,
          category_id: this.group.categories,
          // parent_id: this.group.courses,
          study_from: this.group.study_from,
          study_to: this.group.study_to,
          teacher: this.group.teacher,
          typeOfLesson: this.group.typeOfLesson,
        };
        //console.log(formData);

        let urlToUp = "/api/group/learning";
        this.$store.dispatch("Course/fetchGroup2learnings", formData)
          .catch((error) => console.error(error))
          .finally(() => this.$router.push("/groups/list"));
      }
    },

    cancelBtnHead() {
      //console.log('cancel');
      this.$router.go(-1);
    },

    //---------------------------------------------
    test(array) {
      console.log(array.length);
      var index;
      for (index = 0; index < array.length; ++index) {

        this.selectedOptions = _.find(array, function (selectedOptions) {
          return selectedOptions.id == array[index]
        })
      }
    }

    //---------------------------------------------

  },
};
</script>

<style src="vue3-treeselect/dist/vue3-treeselect.css"></style>

<style>
.v-card-text {
  /* font-size: 1.1rem; */
  font-size: 16px;

}

.vue-treeselect__control {
  height: 56px;
  border-bottom: 1px solid;
  /* box-shadow: 0 3px 1px -2px var(--v-high-emphasis-opacity, rgba(0, 0, 0, 0.2)), 0 2px 2px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.14)), 0 1px 5px 0 var(--v-shadow-key-penumbra-opacity, rgba(0, 0, 0, 0.12)); */
  /* --v-field-padding-top :10px;
  --v-field-padding-start:16px; */
  background: #f4f4f4;
  margin-bottom: 17px;
  border-radius: 5px;
}

.vue-treeselect__placeholder,
.vue-treeselect__single-value {
  padding-left: 12px;
  line-height: 56px;
  color: #848484;

}

.vue-treeselect__control-arrow-container {
  width: 38px;
}

.vue-treeselect:not(.vue-treeselect--disabled) .vue-treeselect__multi-value-item:not(.vue-treeselect__multi-value-item-disabled):hover .vue-treeselect__multi-value-item:not(.vue-treeselect__multi-value-item-new) .vue-treeselect__multi-value-item:not(.vue-treeselect__multi-value-item-new):hover,
.vue-treeselect__multi-value-item {
  cursor: pointer;
  color: #7a7a7a;
  background-color: #f2efef;
  border-bottom: 3px solid;
}



.vue-treeselect--has-value .vue-treeselect__multi-value {
  margin-bottom: 15px;

}

/* --focused */
.vue-treeselect__value-containe:hover {
  background: red;
}
</style>