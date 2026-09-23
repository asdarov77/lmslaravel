<template>
  <div class="card">




    <h2>{{ question.question_text }}</h2>
    <ul>
      <li v-for="answer in question.answers" :key="answer.id">
        <label>
          <input type="radio" :value="answer.id" v-model="selectedAnswers[question.id]">
          {{ answer.answer }}
        </label>
      </li>
    </ul>
    <button @click="nextQuestion">Следующий вопрос</button>
  </div>
</template>

  
<script>
import axios from 'axios';

export default {
  //props: ["idEdit"],
  data() {
    return {
      question: {},
      selectedAnswers: {},
      answerStatus: {},
    };
  },
  mounted() {
    // получение списка вопросов с сервера
    axios.get(`/api/questions/${this.idEdit}`)
      .then(response => {
        this.question = response.data;
      })
      .catch(error => {
        console.log(error);
      });
  },
  methods: {
    nextQuestion() {
      console.log(this.idEdit, "this.idEdit")
      const nextId = this.idEdit + 1;
      this.$router.push({ name: 'question.item', params: { idEdit: nextId } });
      axios.get(`/api/questions/${nextId}`)
        .then(response => {
          this.question = response.data;
        })
        .catch(error => {
          console.log(error);
        });
    },

    checkAnswer(question) {
      // проверка выбранного ответа на правильность
      const selectedAnswerId = this.selectedAnswers[question.id];
      const correctAnswerId = question.answers.find(answer => answer.is_correct).id;
      if (selectedAnswerId === correctAnswerId) {
        this.answerStatus[question.id] = 'Правильно';
      } else {
        this.answerStatus[question.id] = 'Неправильно';
      }
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
.card {
  background-color: #F0F8FF;
  padding: 20px;
  margin: 20px;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
}

button {
  background-color: #4CAF50;
  color: white;
  border: none;
  padding: 10px;
  border-radius: 5px;
  cursor: pointer;
}
</style>