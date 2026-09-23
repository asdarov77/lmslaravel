<template>
  <!-- {{ isFinish }} --isfinish -->
  <!-- {{ selectedAnswers }}-- -->
  <!-- {{ selectedAnswersLength }} отвечено из {{ questions.length }} -->
  <!-- {{ answerStatus }} -->


  <v-card>
    <v-toolbar color="primary">
      <v-toolbar-title>Тестирование </v-toolbar-title>
    </v-toolbar>
    <v-row no-gutters>
      <v-col cols="8">


        <!-- <v-container fluid> <div class="questions"> -->

        <!-- Блок вопросов -->
        <v-sheet elevation="1" color="#f5f5f5" v-if='!isFinishExamClicked' no-gutters class="cards">
          <!-- <div class="question ml-5 mt-1"> -->

          <h2 class="mx-1">{{ question.question_text }}</h2>

          <ul class="mt-5">
            <li v-for="answer in question.answers" :key="answer.id">
              <label>
                <input type="radio" :value="answer.id" v-model="selectedAnswers[question.id]">
                {{ answer.answer }}
              </label>
            </li>
          </ul>



          <v-btn v-if='selectedAnswersLength !== questions.length' class="mt-3 mb-3 ml-3 mr-3" color="primary"
            @click="nextQuestion">Следующий вопрос</v-btn>

          <v-btn v-if='selectedAnswersLength === questions.length === !isFinishExamClicked' class="mt-3 mb-3 ml-3 mr-3"
            @click="finishExam">Закончить тестирование</v-btn>
        </v-sheet>


        <!--                блок финальный результаты                -->
        <v-card v-if='isFinishExamClicked'>
          <v-card-text class="text-center font-weight-bold" style="font-size: 20px;">Тестирование завершено</v-card-text>
          <v-card-text>Всего вопросов: {{ questions.length }}</v-card-text>
          <v-card-text>Правильно отвечено: {{ correctAnswersCount }}</v-card-text>
          <v-card-text>Неправильно отвечено: {{ incorrectAnswersCount }}</v-card-text>

          <my-progress class="text-center" :size="150" :strokeWidth="10" :progressColor="progressColor"
            :successPercentage="successPercentage" :progressIcon="progressIcon"></my-progress>
          <h2>Оценка: {{ getGrade(successPercentage) }}</h2>
          <!-- <h3>Оценка: {{ grade }}</h3> -->
        </v-card>
        <!-- </div> -->
        <!-- </v-container> -->
      </v-col>
      <v-col cols="4">

        <!-- <v-container fluid> -->
        <!-- <div class="questions"> -->
        <!--                      блок карточек с вопросами                     -->

        <!-- <div class="cards">   -->
        <v-sheet rounded elevation="1" color="#f5f5f5">
          <!-- <v-sheet> -->
          <!-- <v-row v-if='!isFinishExamClicked'> -->
          <v-row>
            <!-- <v-col v-for="(question, index) in questions" :key="index" cols="2"> -->

            <v-col v-for="(question, index) in displayedQuestions" :key="index" cols="2" no-gutters>


              <v-card elevation="3" class="ml-2 mt-2 mr-2 mb-2"
                :style="{ backgroundColor: answerColors[question.id] || (selectedAnswers[question.id] !== undefined ? 'gray' : 'white'), color: 'black' }"
                :outlined="current === index || answerStatus[question.id] !== undefined"
                :border-color="answerColors[question.id] || (selectedAnswers[question.id] !== undefined ? 'gray' : 'white')"
                @mouseover="current = index" @mouseleave="current = -1" @click="goToQuestion(question.id)"
                v-bind:class="{ 'active-class': question.id === currentId }">
                <!-- <v-card-text>{{ index + 1 }}</v-card-text> -->
                <v-card-text>{{ questionPosition(index) }}</v-card-text>
              </v-card>
            </v-col>

          </v-row>

          <v-pagination v-model="page" :value="currentPage" :length="totalPages" class="mt-3" rounded="circle"
            @input="updateDisplayedQuestions">
          </v-pagination>

        </v-sheet>
        <!-- </div> -->
        <!-- </v-container> -->

        <!--                  конец    блока карточек с вопросами                     -->

      </v-col>
    </v-row>
  </v-card>
</template>

  
<script>
import $api from "../../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;
import MyProgress from '../VProgressCircular.vue'
export default {
  components: {
    MyProgress
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
      isFinishExamClicked: false,
      current: -1,
      question: [],
      questions: [],
      selectedAnswers: {},
      answerStatus: {},
      nextId: 0, //следующий
      currentId: 0, // текущий
      isFinish: false,
      isAllChecked: false, //все ли вопросы отвечены
      answerColors: {},  // Добавляем новое свойство для хранения цветов ответов
      correctAnswersCount: 0,
      incorrectAnswersCount: 0,
      // пагинация
      page: 1, // текущая страница
      pageSize: 12,
      //
      //grade: null, // Оценка
      // gradeBoundaries: [
      //   { boundary: 0, grade: '2' },
      //   { boundary: 35, grade: '3' },
      //   { boundary: 65, grade: '4' },
      //   { boundary: 85, grade: '5' }
      // ]
      gradeBoundaries: []
    };
  },
  computed: {
    // currentQuestion() {
    //   return this.current >= 0 ? this.questions[this.current] : null
    // }

    // вычисляем количество отвеченных вопросов
    selectedAnswersLength() {
      return Object.keys(this.selectedAnswers).length;
    },

    successPercentage() {
      return Math.round(this.correctAnswersCount / this.questions.length * 100)

    },
    progressColor() {
      if (this.successPercentage >= 70) {
        return 'green'
      } else if (this.successPercentage >= 40) {
        return 'orange'
      } else {
        return 'red'
      }
    },
    progressIcon() {
      if (this.successPercentage >= 70) {
        return '\u263A'
      } else if (this.successPercentage >= 40) {
        return '\uD83D\uDE10'
      } else {
        return '\u2639'
      }
    },
    totalPages() {
      return Math.ceil(this.questions.length / this.pageSize);
    },
    displayedQuestions() {
      const startIndex = (this.page - 1) * this.pageSize;
      return this.questions.slice(startIndex, startIndex + this.pageSize);
    },
    currentPage() {
      return Math.ceil(this.currentId / this.pageSize);
    }
  },




  mounted() {
    console.log(this.idEdit, "idEdit")
    console.log(this.idCategory, "cat")

    // получение списка вопросов с сервера
    $api.get(apiUrl + "/api/questions?aukstructure_id=" + this.idEdit + "&category_id=" + this.idCategory)
      //$api.get(apiUrl + '/api/questions')
      .then(response => {
        //this.questions = response.data.slice(0, 6);
        this.questions = response.data;
        this.goToQuestion(this.questions[0].id); // переходим к первому вопросу 
      })
      .catch(error => {
        console.log(error);
      });

    $api.get(apiUrl + '/api/grade-boundary')
      .then(response => {
        this.gradeBoundaries = response.data;
      })
      .catch(error => {
        console.log(error);
      });
  },
  methods: {
    getGrade(percentage) {
      console.log(percentage, "percentage")
      for (let i = this.gradeBoundaries.length - 1; i >= 0; i--) {
        if (percentage >= this.gradeBoundaries[i].boundary) {
          return this.gradeBoundaries[i].grade;
        }
      }
      return 'N/A'; // Если процент не соответствует ни одной границе
    },

    // selectQuestion(index) {
    //   this.current = index
    // },
    updateDisplayedQuestions() {
      // ничего не нужно делать, так как отображаемые вопросы уже вычислены в computed свойстве
    },
    // вычисление позиции вопроса в общем списке вопросов
    questionPosition(index) {
      const startIndex = (this.page - 1) * this.pageSize;
      return startIndex + index + 1;
    },
    finishExam() {
      console.log('завершено тестирование')
      this.checkAnswers();
      this.currentId = null;
      console.log(this.isFinishExamClicked, 'this.isFinishExamClicked')
      this.isFinishExamClicked = true;
      console.log(this.isFinishExamClicked, 'after this.isFinishExamClicked')

    },
    checkAnswers() {
      this.questions.forEach(question => {
        const selectedAnswerId = this.selectedAnswers[question.id];
        const correctAnswerId = question.answers.find(answer => answer.is_correct).id;
        if (selectedAnswerId === correctAnswerId) {
          this.answerStatus[question.id] = 'Правильно';
          this.answerColors[question.id] = 'green';
          this.correctAnswersCount++;
        } else {
          this.answerStatus[question.id] = 'Неправильно';
          this.answerColors[question.id] = 'red';
          this.incorrectAnswersCount++;
        }
      });

      // Выставление оценки
      // const totalQuestions = this.questions.length;
      // const passPercentage = 70; // Проходной балл в процентах
      // const percentageCorrect = (this.correctAnswersCount / this.questions.length) * 100;
      // if (percentageCorrect >= passPercentage) {
      //   this.grade = 'Прошел';
      // } else {
      //   this.grade = 'Не прошел';
      // }


    },
    // checkAnswer(question) {
    //   // проверка выбранного ответа на правильность
    //   const selectedAnswerId = this.selectedAnswers[question.id];
    //   const correctAnswerId = question.answers.find(answer => answer.is_correct).id;
    //   if (selectedAnswerId === correctAnswerId) {
    //     this.answerStatus[question.id] = 'Правильно';
    //   } else {
    //     this.answerStatus[question.id] = 'Неправильно';
    //   }
    // },
    goToQuestion(idEdit) {

      this.currentId = idEdit
      //this.$router.push({ name: 'question.item', params: { idEdit } });      
      $api.get(apiUrl + `/api/questions/${this.currentId}`)
        .then(response => {
          this.question = response.data; // обернуть ответ в массив, чтобы использовать v-for
        })
        .catch(error => {
          console.log(error);
        });
      if (this.currentId < this.questions.length) {
        this.nextId = this.currentId + 1;
        this.isFinish = false;
      }
      else {
        this.nextId = this.currentId;
        this.isFinish = true;
      }
    },
    nextQuestion() {

      //const nextId = this.current +1
      //console.log(this.current, 'this.current')
      //console.log(this.idEdit, "this.idEdit")
      this.goToQuestion(this.nextId);
      this.page = Math.ceil(this.nextId / this.pageSize);
    },


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
      $api.post(apiUrl + '/api/student-answers', { studentAnswers })
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

.questions .cards {
  /* min-height: 200px;  */
  display: flex;
  flex: 1;
  height: 100%;
}
</style>
