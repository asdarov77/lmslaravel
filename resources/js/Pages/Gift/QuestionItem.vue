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
    <button @click="nextQuestion">{{ $t('gift.nextQuestion') }}</button>
  </div>
</template>

  
<script>
import axios from 'axios';
import { unwrapResponse } from "../../api/envelope";

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
        this.question = unwrapResponse(response);
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
          this.question = unwrapResponse(response);
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

<!--
  Блок был unscoped, и это било по всему приложению: селектор `button`
  красил ВСЕ кнопки без класса Vuetify в зелёный с белым текстом и
  отступами 10px, а `.card` — все элементы с таким классом в других
  страницах. Здесь и карточка, и кнопка — свои элементы этого шаблона,
  поэтому достаточно scoped.
-->
<style scoped>
.card {
  background-color: var(--c-info-soft);
  padding: var(--sp-5);
  margin: var(--sp-5);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-2);
}

button {
  background-color: var(--c-success);
  color: var(--c-on-primary);
  border: none;
  padding: var(--sp-2) var(--sp-3);
  border-radius: var(--radius-md);
  cursor: pointer;
}
</style>