<template>
  <!-- {{ isFinish }} --isfinish -->
  <!-- {{ selectedAnswers }}-- -->
  <!-- {{ selectedAnswersLength }} отвечено из {{ questions.length }} -->
  <!-- {{ answerStatus }} -->


  <div class="u-page">
  <!--
    Заголовок страницы добавлен: страница прохождения экзамена
    начиналась сразу с вопроса, и единственным заголовком был h2
    вопроса. Заголовка уровня 1 не было вовсе — то есть у страницы не
    было имени ни для человека, ни для навигации по заголовкам
    (WCAG 2.2 SC 1.3.1, 2.4.6).
  -->
  <PageHeader :title="$t('exams.take.title')" />

  <v-card class="u-card u-card--elevated">
    <v-row no-gutters>
      <v-col cols="8">


        <!-- <v-container fluid> <div class="questions"> -->

        <!-- Блок вопросов -->
        <v-sheet elevation="1" color="var(--c-surface-2)" v-if='!isFinishExamClicked' no-gutters class="cards">
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
            @click="nextQuestion">{{ $t('gift.nextQuestion') }}</v-btn>

          <v-btn v-if='selectedAnswersLength === questions.length === !isFinishExamClicked' class="mt-3 mb-3 ml-3 mr-3"
            @click="finishExam">{{ $t('gift.finishExam') }}</v-btn>
        </v-sheet>


        <!--                блок финальный результаты                -->
        <v-card v-if='isFinishExamClicked'>
          <v-card-text class="text-center font-weight-bold exam-done-title">{{ $t('gift.finished') }}</v-card-text>
          <v-card-text>{{ $t('gift.totalQuestions', { n: questions.length }) }}</v-card-text>
          <v-card-text>{{ $t('gift.correctCount') }}: {{ correctAnswersCount }}</v-card-text>
          <v-card-text>{{ $t('gift.incorrectCount') }}: {{ incorrectAnswersCount }}</v-card-text>

          <my-progress class="text-center" :size="150" :strokeWidth="10" :progressColor="progressColor"
            :successPercentage="successPercentage" :progressIcon="progressIcon"></my-progress>
          <h2>{{ $t('gift.grade') }}: {{ getGrade(successPercentage) }}</h2>
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
        <v-sheet rounded elevation="1" color="var(--c-surface-2)">
          <!-- <v-sheet> -->
          <!-- <v-row v-if='!isFinishExamClicked'> -->
          <v-row>
            <!-- <v-col v-for="(question, index) in questions" :key="index" cols="2"> -->

            <v-col v-for="(question, index) in displayedQuestions" :key="index" cols="2" no-gutters>


              <v-card elevation="3" class="ml-2 mt-2 mr-2 mb-2 exam-q"
                :class="`exam-q--${answerState(question)}`"
                :outlined="current === index || answerStatus[question.id] !== undefined"
                :border-color="`var(--c-${answerState(question)})`"
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
  </div>
</template>

  
<script>
import $api from "../../api/httpClient";
import { unwrapResponse, unwrapArray, numericQuery } from "../../api/envelope";
const apiUrl = import.meta.env.VITE_APP_URL;
import MyProgress from '../VProgressCircular.vue'
import PageHeader from '../../components/ui/PageHeader.vue'
export default {
  components: {
    PageHeader,
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
      // Вердикт по вопросу: 'correct' | 'wrong'. Раньше здесь лежали
      // русские строки «Правильно»/«Неправильно», которые нигде не
      // показывались, а рядом держалась вторая карта для цветов. Обе
      // сводились к одному ключу — иначе состояния разъезжались бы.
      answerStatus: {},
      nextId: 0, //следующий
      currentId: 0, // текущий
      isFinish: false,
      isAllChecked: false, //все ли вопросы отвечены
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
      // Имена цветов темы Vuetify, а не 'green'/'orange'/'red' из
      // палитры браузера: тема собрана из тех же токенов, поэтому цвет
      // следует за светлой/тёмной, а не остаётся чужой константой.
      if (this.successPercentage >= 70) {
        return 'success'
      } else if (this.successPercentage >= 40) {
        return 'warning'
      } else {
        return 'error'
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
    // Получение списка вопросов с сервера.
    // Фильтры добавляются ТОЛЬКО когда они заданы: раньше строка собиралась
    // конкатенацией и уходила как /api/questions?aukstructure_id=NaN&category_id=NaN,
    // потому что parseInt(undefined) даёт NaN. Бэкенд отвечал на это 422,
    // и страница «Вопросы» сыпала ошибкой в консоль при обычном переходе.
    const filters = numericQuery({
      aukstructure_id: this.idEdit,
      category_id: this.idCategory,
    });

    $api.get(apiUrl + "/api/questions", { params: filters })
      //$api.get(apiUrl + '/api/questions')
      .then(response => {
        //this.questions = response.data.slice(0, 6);
        this.questions = unwrapArray(response);
        if (this.questions[0]) this.goToQuestion(this.questions[0].id); // переходим к первому вопросу 
      })
      .catch(() => {
        // Причина уже показана тостом в перехватчике httpClient.
        // Дублировать её в консоль незачем, а пустой список вопросов
        // отрисовывается как пустое состояние.
      });

    $api.get(apiUrl + '/api/grade-boundary')
      .then(response => {
        this.gradeBoundaries = unwrapArray(response);
      })
      .catch(() => {
        // Без шкалы оценок getGrade вернёт 'N/A' — экзамен остаётся
        // проходимым. Тост приходит из перехватчика.
      });
  },
  methods: {
    getGrade(percentage) {
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
      this.checkAnswers();
      this.currentId = null;
      this.isFinishExamClicked = true;
    },
    /**
     * Состояние карточки вопроса в навигаторе экзамена.
     *
     * Возвращает ключ словаря токенов, а не цвет. Раньше здесь стояло
     * `backgroundColor: 'green' | 'red' | 'gray' | 'white'` инлайново,
     * вместе с `color: 'black'`. Это давало три проблемы: белые карточки
     * в тёмной теме (белое пятно на тёмном экране), текст всегда чёрный —
     * то есть на тёмно-зелёной заливке он не читался, и цвета из палитры
     * браузера, не связанные с токенами проекта.
     *
     * Теперь состояние — правило в CSS, а окраска берётся из токенов,
     * которые уже умеют меняться вместе с темой.
     */
    answerState(question) {
      const verdict = this.answerStatus[question.id];

      if (verdict === 'correct') return 'success';
      if (verdict === 'wrong') return 'danger';

      return this.selectedAnswers[question.id] !== undefined ? 'answered' : 'unanswered';
    },

    checkAnswers() {
      this.questions.forEach(question => {
        const selectedAnswerId = this.selectedAnswers[question.id];
        const correctAnswerId = question.answers.find(answer => answer.is_correct).id;
        if (selectedAnswerId === correctAnswerId) {
          this.answerStatus[question.id] = 'correct';
          this.correctAnswersCount++;
        } else {
          this.answerStatus[question.id] = 'wrong';
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
          this.question = unwrapResponse(response); // обернуть ответ в массив, чтобы использовать v-for
        })
.catch(error => {
        // Причина уже показана тостом в перехватчике httpClient:
        // дублировать её в консоль незачем. Пустой список вопросов
        // отрисовывается как пустое состояние.
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
        .catch(error => {
          // Причина уже показана тостом в перехватчике httpClient.
        });
    },
  },
};
</script>


<!--
  Блок unscoped, а селекторы здесь общие на вид — `.active-class` и
  `.square` так и называются в других проектах, и любая страница,
  где появится класс с таким именем, получила бы рамку и скругление
  от экзамена. Все три класса принадлежат только этому шаблону.
-->
<style scoped>
/*
  Карточки навигатора по вопросам.

  Фон — «мягкий» токен, текст и рамка — основной. В тёмной теме токены
  разъезжаются в тёмные тона с светлым текстом, поэтому контраст держится
  сам, без отдельной ветки для тёмной темы.
*/
.exam-q--success {
  background: var(--c-success-soft);
  color: var(--c-success);
}

.exam-q--danger {
  background: var(--c-danger-soft);
  color: var(--c-danger);
}

.exam-q--answered {
  background: var(--c-surface-3);
  color: var(--c-text-secondary);
}

.exam-q--unanswered {
  background: var(--c-surface);
  color: var(--c-text-secondary);
}

/* Ошибка проверки не должна теряться на карточке вопроса. */
.exam-q--danger .v-card-text,
.exam-q--success .v-card-text {
  color: inherit;
}

/* Заголовок итога экзамена. Раньше кегль стоял инлайном в style=. */
.exam-done-title {
  font-size: var(--fs-xl);
}

.square {
  height: 50px;
  display: flex;
  justify-content: center;
  align-items: center;
}

.active-class {
  border-radius: var(--radius-lg);
  /* Раньше стоял синий из палитры браузера: он не переключался в тёмной
     теме и не совпадал ни с одним токеном. */
  border: 3px solid var(--c-primary);
}

.questions .cards {
  /* min-height: 200px;  */
  display: flex;
  flex: 1;
  height: 100%;
}
</style>
