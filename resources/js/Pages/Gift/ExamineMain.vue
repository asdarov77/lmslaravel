

<template>
  <v-card elevation="5" class="mx-auto" title="Банк вопросов">
    <!-- ####################      v-select      ###################################### -->
    <v-row class="ml-3 mr-3 mt-1">
      <v-col cols="6">
        <v-select v-model="current_category" :items="categories" label="Выберите категорию" item-value="id"
          item-title="title" @update:modelValue="() => {
            getThemes()
            current_auk_theme = null
          }" />
      </v-col>
      <v-col cols="6">
        <v-select v-model="current_auk_theme" :items="auk" item-text="title" item-value="aukstructure_id"
          label="Выберите тему" @update:modelValue="getQuestions" />
      </v-col>
    </v-row>
    <!-- ####################      таблица      ###################################### -->
    <v-row class="ml-3 mr-3">
      <v-col cols="12">
        <v-table v-if="showTable" style="width: 100%">
          <thead>
            <tr>
              <th>Номер</th>
              <th>Вопрос</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in displayedQuestions" :key="index">
              <td align="center">{{ (page - 1) * pageSize + index + 1 }}</td>
              <td>{{ item.question_text }}</td>
              <td align="center"> <v-icon small color="green" class="mr-2" @click="
                this.$router.push({
                  name: 'question.edit',
                  params: { idEdit: item.id },
                })
                ">
                  mdi-pencil
                </v-icon>
                <v-icon small @click="deleteItem(item.id)" color="red">
                  mdi-delete
                </v-icon>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-col>
    </v-row>
    <!-- ####################      подвал      ###################################### -->
    <v-row v-if="showTable" class="ml-3 mr-3">
      <v-col cols="2">
        <v-btn color="primary"
          @click="$router.push({ name: 'question.new', params: { category_id: this.current_category, aukstructure_id: this.current_auk_theme }, })">
          + вопрос
        </v-btn>
      </v-col>
      <v-col cols="9">
        <v-pagination v-model="page" :value="currentPage" :length="totalPages" class="mt-1" rounded="circle"
          @input="updateDisplayedQuestions">
        </v-pagination>
      </v-col>
      <v-col cols="1">
        <v-select label="Строк" :items="[10, 20, 50, 100]" v-model="pageSize">
        </v-select>
      </v-col>
    </v-row>
  </v-card>
</template>

  
<script>

import $api from "../../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;
import MyProgress from '../VProgressCircular.vue'
import { mapState, mapGetters } from "vuex";
export default {
  components: {
    MyProgress,
    // VDataTable

  },
  props: {
    idEdit: {
      type: Number,
      required: true,
    },
    idCategory: {
      type: Number,
      required: true,
    },
    treeData: Object,
  },

  data() {

    return {
      themesOfQuestions: [],
      aukstructures: [],
      questions: [],
      current_category: null,
      current_auk_theme: null,
      showTable: false,
      auk: [],

      // пагинация
      page: 1, // текущая страница
      pageSize: 10,
    };
  },
  computed: {
    ...mapState("Course", ["courses", "category", "totalCourses", "course"]),
    ...mapGetters("Course", ["categories"]),

    totalPages() {
      return Math.ceil(this.questions.length / this.pageSize);
    },
    displayedQuestions() {
      const startIndex = (this.page - 1) * this.pageSize;
      return this.questions.slice(startIndex, startIndex + this.pageSize);
    },
    currentPage() {
      return Math.ceil(this.currentId / this.pageSize);
    },


  },


  mounted() {
    this.$store.dispatch("Course/fetchCategories");

    //console.log(this.idEdit, "idEdit")
    //console.log(this.idCategory, "idCategory")
    $api.get(apiUrl + '/api/aukstructure')
      .then(response => {
        this.aukstructures = response.data;
      })
      .catch(error => {
        console.log(error);
      });


    // получение списка вопросов с сервера

  },
  methods: {
    getThemes() {

      $api.get('/api/questions', {
        params: {
          category_id: this.current_category,
        }
      })
        .then(response => {
          this.auk = response.data
            .filter(question => question.category_id === this.current_category)
            .reduce((uniqueTitles, question) => {
              const { title, aukstructure_id } = question;
              const existingQuestion = uniqueTitles.find(q => q.title === title);
              if (!existingQuestion) {
                uniqueTitles.push({ title, aukstructure_id });
              }
              return uniqueTitles;
            }, []);

        })
        //.finally(this.showTable === true)   
        .catch(error => {
          console.log(error);
        });
    },




    getQuestions() {
      console.log(this.current_category, "category this.cat")
      console.log(this.current_auk_theme, "this.current_auk_theme.id")
      $api.get('/api/questions', {
        params: {
          category_id: this.current_category,
          aukstructure_id: this.current_auk_theme
        }
      })
        .then(response => {
          //console.log(this.questions,"questions")
          this.questions = response.data;
          this.showTable = true
          //console.log(this.showTable,"this.showTable" )         
        })
        .catch(error => {
          console.log(error);
        });

    },


    async deleteItem(id) {
      // Логика удаления элемента      
      try {
        await $api.delete(`/api/questions/${id}`)
        await this.getQuestions()
      } catch (error) {
        console.log(error)
      }
    },
    updateDisplayedQuestions() {
      // ничего не нужно делать, так как отображаемые вопросы уже вычислены в computed свойстве
    },

    // goToQuestion(idEdit) {

    //   this.currentId = idEdit
    //   //this.$router.push({ name: 'question.item', params: { idEdit } });      
    //   axios.get(`/api/questions/${this.currentId}`)
    //     .then(response => {
    //       this.question = response.data; // обернуть ответ в массив, чтобы использовать v-for
    //     })
    //     .catch(error => {
    //       console.log(error);
    //     });
    //   if (this.currentId < this.questions.length) {
    //     this.nextId = this.currentId + 1;
    //     this.isFinish = false;
    //   }
    //   else {
    //     this.nextId = this.currentId;
    //     this.isFinish = true;
    //   }
    // },



    submitTest() {
      // отправка результатов теста на сервер
      const studentAnswers = [];
      for (const questionId in this.selectedAnswers) {
        const studentAnswer = {
          question_id: questionId,
          answer_id: this.selectedAnswers[questionId],
        };
        studentAnswers.push(studentAnswer);
      }
      axios.post('/api/student-answers', { studentAnswers })
        .then(response => {
          console.log(response);
        })
        .catch(error => {
          console.log(error);
        });
    },
  },
};
</script>


<style>
.square {
  height: 50px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.active-class {
  border-radius: 15px;
  border: 3px solid blue;
}
</style>



      <!-- <table>
  <thead>
    <tr>
      <th width="1">Номер</th>
      <th width="10">Вопрос</th>
      <th width="1">Действия</th>
    </tr>
  </thead>
  <tbody>
    <tr v-for="(item, index) in displayedQuestions" :key="index">
      <td width="1">{{ (page - 1) * pageSize + index + 1 }}</td>
      <td width="10">{{ item.question_text }}</td>
      <td width="1">
        <v-icon small class="mr-2" @click="
          this.$router.push({
            name: 'question.edit',
            params: { idEdit: item.id },
          })
        ">
          mdi-pencil
        </v-icon>
        <v-icon small @click="deleteItem(item.id)">
          mdi-delete
        </v-icon>
      </td>
    </tr>
  </tbody>        
</table> -->

<!-- //   <v-pagination
//   v-model="currentPage"
//   :length="Math.ceil(questions.length / perPage)"
//   :total-visible="5"
// ></v-pagination> 
//subtitle="..."
// item-text="categories.title"
//       item-value="categories.id"

//import { VDataTable } from 'vuetify/labs/VDataTable' -->