<template>  
  <v-btn color="success" class="mb-3" :to="{ name: 'groups.create' }"  
  >Добавить группу</v-btn
  >
  <v-table dense class="elevation-1 cursor-pointer">
    <template v-slot:default>      
      <thead>
        <tr>
          <th class="text-left">id</th>
          <th class="text-left">Наименование</th>
          <th class="text-left">Описание</th>
          <th class="text-left">Курсы</th>
          <th class="text-left">Категория</th>
          <th class="text-left">Период</th>
          <th class="text-left">Редактировать</th>
          <th class="text-left">Удалить</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in allGroups" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.groupname }}</td>
          <td>{{ item.groupdescription }}</td>
          <!-- в vuex индекс начинается с нуля, в БД с 1,поэтому cat[id-1] -->
          <td>
            <ul>

            <li v-for="_item in item.group2learnings" :key="_item.id">
              <!-- {{ _item.course_id? courses[_item.course_id-1].title: "" }} -->
            </li>
          </ul>
          </td>
<!-- в vuex индекс начинается с нуля, в БД с 1,поэтому cat[id-1] -->
          <td>
            <ul>
            <li v-for="_item in item.group2learnings" :key="_item.id" >
              {{ _item.category_id? categories[_item.category_id-1].title: "" }}                            
            </li>
          </ul>
          </td>

          <td>
            <ul>
            <li v-for="_item in item.group2learnings" :key="_item.id">
              {{ _item.study_from }}-{{ _item.study_to }}
            </li>
          </ul>
          </td>
           <!-- <td>
            {{ item.study_from }}-{{ item.study_to }}</td> -->
          <td>
            <v-btn color="success"
              :to="{ name: 'groups.update', params: { idEdit: item.id } }"
              class="my-1"
            >
              Редактировать</v-btn
            >
          </td>
          <td>
            <v-btn @click="dialog = !dialog" color="error" class="my-1">Удалить</v-btn>
            
            <Dialog
              :dialog="dialog"
              :title="'точно удалить'"
              :text="'удалить группу ?'"
              @dialogClose="dialog = false"
              @confirm="deleteGroup(item.id)"              
            >
            </Dialog>
          
          </td>
        </tr>
      </tbody>
    </template>
  </v-table>{{hasPermission(['manage-users'],'Manage users')}}
  
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import Dialog from "./Modal/Dialog.vue";

export default {
  components: { Dialog },
  data() {
    return {
      showDialog: false,      
      dialog: false,
      overlay: false,
      isLoading: false,   
      //allGroups: [],   
      //totalGroups: '',
      catFilter: ''
    };
  },
  // created() {
  //   //console.log("created");
  //   // или this.$store.dispatch
  //   //store.dispatch("Groups/fetchGroups"); // выполнить action,вызываем dispatch,параметром название action
  // },
  async created() {
    this.$store.dispatch("Course/fetchCourses");
    this.$store.dispatch("Course/fetchCategories");
    //console.log('mounted group')
    await this.$store
        .dispatch('User/fetchGroups')                
        .catch(error => console.error(error))
        .finally(() => (this.isLoading = false))
  },


  
  computed: {       
     ...mapState('User', ['totalGroups', 'allGroups']),
     ...mapState('Course', ['totalCourses','totalCategories', 'courses', 'course', 'categories']),
     ...mapGetters('User', ['groups']),
     ...mapGetters("Auth", ["hasPermission"]),
     ...mapGetters("Course", ["courses", "categories"])
  },

  methods: {    
    cancel() {
      //console.log("cancel");
      this.showDialog = false;
      this.showDialogUser = false;
    },
    //-------!! temporary !! загрузка групп записанных на курсы с group2learning-----------
    // returnCategories(cat_id) {
    //   this.catFilter = this.categories.filter(function (e) {
    //     return e.id === cat_id;
    //   });
    // },
    //-----------конец загрузки  -------
    // confirm() {
    //   console.log('confirm');
    //   this.showDialog = false;
    // },
    // confirmUser() {
    //   console.log('confirm user');
    //   this.showDialogUser = false;
    // },

    async deleteGroup(group_id) {
      //console.log(group_id); 
      
      this.$store.dispatch('User/deleteGroup', group_id).catch(error => console.error(error))
      this.$store
        .dispatch('User/fetchGroups')
        .catch(error => console.error(error))
        .finally(() => (console.log("удалена группа  ",group_id) ))      
    },
  },
};
</script>

  
