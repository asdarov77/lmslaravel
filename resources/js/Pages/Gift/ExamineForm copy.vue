<template>
  <!-- {{ isFinish }} --isfinish -->
  <!-- {{ selectedAnswers }}-- -->
  {{ selectedAnswersLength }} отвечено из {{ questions.length }}
  <v-card>
    <v-toolbar color="primary">
      <v-toolbar-title>Тестирование </v-toolbar-title>
    </v-toolbar>
    <v-row>
      <v-col cols="8">
        <v-sheet elevation="1" color="#f5f5f5">
          <!-- {{ question.id }} -->
          <h2 class="mx-1">{{ question.question_text }}</h2>
          <ul>
            <li v-for="answer in question.answers" :key="answer.id">
              <label>
                <input type="radio" :value="answer.id" v-model="selectedAnswers[question.id]">
                {{ answer.answer }}
              </label>
            </li>
          </ul>
        
          <v-btn v-if=' selectedAnswersLength !== questions.length' class="mt-3 mb-3 ml-3 mr-3" color="primary" @click="nextQuestion">Следующий вопрос</v-btn>
        
          <v-btn v-if='selectedAnswersLength === questions.length' class="mt-3 mb-3 ml-3 mr-3" @click="finishExam">Закончить тестирование</v-btn>

        </v-sheet>
      </v-col>
      <v-col cols="4" >
        <v-sheet  class='ml-3 mr-3 mb-3 mt-3' elevation="1" color="#f5f5f5">
          <v-row>
            <v-col v-for="(question, index) in questions" :key="index" cols="2">
              <v-sheet class="square" outlined 
              :outlined="current === index"
              :style="{ backgroundColor: current === index ? '#3f51b5' : (selectedAnswers[question.id] !== undefined ? 'gray' : 'white'), color: current === index ? 'white' : 'black' }"
  @mouseover="current = index" @mouseleave="current = -1" @click="goToQuestion(question.id)">
                <div class="number">{{ index + 1 }}</div>
              </v-sheet>

              


            </v-col>
          </v-row>
        </v-sheet>
      </v-col>
    </v-row>
  </v-card>

  <!-- <div>
    <h1>Тестирование</h1>
    <div v-for="(question, index) in questions" :key="question.id">
      <h2>{{ index + 1 }}. {{ question.question_text }}</h2>
      <ul>
        <li v-for="answer in question.answers" :key="answer.id">
          <label>
            <input type="radio" :value="answer.id" v-model="selectedAnswers[question.id]">
            {{ answer.answer }}--{{ question.id }}
          </label>
        </li>
      </ul>
      <v-btn @click="checkAnswer(question)">Ответить</v-btn>
      <v-btn @click="goToQuestionItem(question.id)">Вопрос {{ question.id }}</v-btn>
      <div v-if="answerStatus[question.id]">{{ answerStatus[question.id] }}</div>
    </div>
    <button @click="submitTest">Завершить тест</button>
  </div> -->
</template>

  
<script>

import axios from 'axios';

export default {

  data() {
    return {
      current: -1,
      question: [],
      questions: [],
      selectedAnswers: {},
      answerStatus: {},
      nextId: 0, //следующий
      currentId: 0, // текущий
      isFinish: false,
      isAllChecked: false, //все ли вопросы отвечены
      answerColors: {} ,  // Добавляем новое свойство для хранения цветов ответов
    };
  },
  computed: {
    // currentQuestion() {
    //   return this.current >= 0 ? this.questions[this.current] : null
    // }

    // вычисляем количество отвеченных вопросов
    selectedAnswersLength() {
    return Object.keys(this.selectedAnswers).length;
  }
  },
  mounted() {
    // получение списка вопросов с сервера
    axios.get('/api/questions')
      .then(response => {
        this.questions = response.data.slice(0, 10);
        this.goToQuestion(this.questions[0].id); // переходим к первому вопросу 
      })
      .catch(error => {
        console.log(error);
      });
  },
  methods: {
    // selectQuestion(index) {
    //   this.current = index
    // },
    finishExam() {
      console.log('завершено тестирование')
      this.checkAnswers();
    },
    checkAnswers() {
    this.exam.questions.forEach(question => {
      const selectedAnswerId = this.selectedAnswers[question.id];
      const correctAnswerId = question.answers.find(answer => answer.is_correct).id;
      if (selectedAnswerId === correctAnswerId) {
        this.answerStatus[question.id] = 'Правильно';
        this.answerColors[question.id] = 'green';
      } else {
        this.answerStatus[question.id] = 'Неправильно';
        this.answerColors[question.id] = 'red';
      }
    });
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
      console.log(idEdit, "вопрос номер")
      this.currentId = idEdit
      //this.$router.push({ name: 'question.item', params: { idEdit } });      
      axios.get(`/api/questions/${this.currentId}`)
        .then(response => {
          this.question = response.data; // обернуть ответ в массив, чтобы использовать v-for
        })
        .catch(error => {
          console.log(error);
        });
        if(this.currentId < this.questions.length)
        {
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

      //this.$router.push({ name: 'question.item', params: { idEdit: nextId } });
      //   axios.get(`/api/questions/${this.nextId}`)
      //   .then(response => {
      //     this.question = response.data;
      //   })
      //   .catch(error => {
      //     console.log(error);
      //   });
      //   this.idEdit = this.nextId+1;
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

.number {
  font-size: 20px;
}
</style>
