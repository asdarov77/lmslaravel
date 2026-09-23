

<template >
  <!-- {{ editedQuestion.answers.length }} ------ -->
  <!-- {{ editedQuestion }} --- {{ correctAnswerIndex }} --длина ответов ---- {{ editedQuestion.answers.length }} -->
  <!-- <v-card elevation="4" title="Редактирование вопроса">    

    <v-form @submit.prevent="saveChanges" ref="form">
      <v-row class="ml-3 mr-3">
        <v-col cols="12">
          <v-text-field v-model="editedQuestion.question_text" label="Текст вопроса" @input="checkChanges"
            :rules="[value => value.length >= 10 || 'Текст не менее 10 символов']">
          </v-text-field>
        </v-col>
      </v-row>

      <v-row v-for="(answer, index) in editedQuestion.answers" :key="index" class="ml-3 mr-3">
        <v-col cols="1">
          <v-radio-group v-model="editedQuestion.correctAnswerIndex">
            <v-radio :label="'Ответ ' + (index + 1)" :value="index" @input="checkChanges()"
              :rules="[value => value.length >= 2 || 'Текст не менее 2 символов']">
            </v-radio>
          </v-radio-group>
        </v-col>

        


        <v-col cols="11">

          <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="updateCorrectAnswer()"
            :rules="[
             
              value => {
                return editedQuestion.answers.every(answer => answer.length >= 2)
                  || 'Каждый ответ должен содержать не менее двух символов'
              }
            ]"> {{ answer.length }}
            <template v-slot:append>
              <v-btn v-if="showRemoveButtons" @click="removeAnswer(index)" icon color="error">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </template>
          </v-text-field>
        </v-col>

        


      </v-row>
      <v-row class="ml-3 mr-3 mb-3">
        <v-col cols="12">
          <v-btn @click="addAnswer" class="mr-2" color="primary">Добавить ответ</v-btn>
          <v-btn @click="toggleRemoveButtons" class="mr-2" color="error">Удалить ответ</v-btn>
          <v-btn v-if="saveChangesButtons" @click="saveChanges" class="mr-2" color="primary">Сохранить
            изменения</v-btn>
        </v-col>
      </v-row>
    </v-form>
    <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert" :alertFalse="alertFalse">
    </popup>
  </v-card> -->

  <!-- ############################################################################################################# -->

  <v-card elevation="4" title="Редактирование вопроса"> {{ editedQuestion.answers }}

    <v-form @submit.prevent="saveChanges" ref="form">
      <v-row class="ml-3 mr-3">
        <v-col cols="12">
          <v-text-field v-model="editedQuestion.question_text" label="Текст вопроса" :rules=questionRules>
          </v-text-field>
        </v-col>
      </v-row>

      <v-row v-for="(answer, index) in  editedQuestion.answers " :key="index" class="ml-3 mr-3">
        <v-col cols="1">
          <v-radio-group v-model="editedQuestion.correctAnswerIndex" @change="updateCorrectAnswer"
            :rules=answerCountRules>
            <v-radio :label="'Ответ ' + (index + 1)" :value="index">
            </v-radio>
          </v-radio-group>
        </v-col>

        <!-- ответы -->


        <v-col cols="11">
          <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" :rules=answerRules>
            <template v-slot:append>
              <v-btn v-if="showRemoveButtons" @click="removeAnswer(index)" icon color="error">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </template>
          </v-text-field>
        </v-col>

        <!-- конец ответы -->

      </v-row>
      <v-row class="ml-3 mr-3 mb-3">
        <v-col cols="12">
          <v-btn @click="addAnswer" class="mr-2" color="primary">Добавить ответ</v-btn>
          <v-btn @click="toggleRemoveButtons" class="mr-2" color="error">Удалить ответ</v-btn>
          <v-btn v-if="saveChangesButtons" @click="saveChanges" class="mr-2" color="primary">Сохранить
            изменения</v-btn>
        </v-col>
      </v-row>
    </v-form>
    <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert" :alertFalse="alertFalse">
    </popup>
  </v-card>
</template>


<script>
import $api from "../../api/httpClient";
import popup from "../Popup.vue";
import { required, minLength, minValue, between, oneOf } from 'vuelidate/lib/validators'
export default {
  components: {
    popup
  },
  props:
  {
    category_id: {
      type: Number,
      required: true
    },
    aukstructure_id: {
      type: Number,
      required: true
    },
  },
  data() {

    return {
      editedQuestion: {
        question_text: "",
        category_id: this.category_id,
        aukstructure_id: this.aukstructure_id,
        answers: [],
        //correctAnswerIndex: null,
      },
      correctAnswerIndex: 0,
      showRemoveButtons: false,
      saveChangesButtons: false,

      //---- for popup
      isLoading: false,
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
      //isFormCorrect: false,

      questionRules: [
        value => !!value || 'Обязательное поле',
        value => (value && value.length >= 5) || 'Текст должен содержать не менее 5 символов'
      ],
      answerRules: [
        value => !!value || 'Обязательное поле',
        value => (value && value.length >= 2) || 'Текст должен содержать не менее 2 символов',
        () => this.checkUniqueAnswers() || 'Ответы не должны повторяться',
      ],
      answerCountRules: [
        () => this.editedQuestion.answers.length >= 2 || 'Не менее двух ответов',
        () => this.editedQuestion.answers.some(a => a.is_correct) || 'Выберите правильный ответ'
      ]
    };
  },

  methods: {
    checkUniqueAnswers() {
      const answers = this.editedQuestion.answers.map(a => a.answer);
      const uniqueAnswers = new Set(answers);
      return uniqueAnswers.size === answers.length;
    },
    updateCorrectAnswer() {
      this.editedQuestion.answers.forEach(answer => {
        answer.is_correct = false
      })
      const selectedAnswer = this.editedQuestion.answers[this.editedQuestion.correctAnswerIndex]
      selectedAnswer.is_correct = true
    },
    checkChanges() {
      this.saveChangesButtons = true;
    },
    toggleRemoveButtons() {
      this.showRemoveButtons = !this.showRemoveButtons;
    },
    toggleChangesBtn() {
      this.saveChangesButtons = !this.saveChangesButtons;
    },
    saveChanges() {

      this.$refs.form.validate()
        .then(isValid => {
          //console.log(isValid, "isValid")
          if (isValid.valid) {
            console.log("форма валидна")
            $api.post(`/api/questions/`, this.editedQuestion)
              .then(response => {
                console.log(response);
                this.$router.push({ name: 'questions.main' });
              })
              .catch(error => {
                console.log(error);
              });
            this.saveChangesButtons = false

          }
          else {
            console.log("форма не валидна")
          }
        })
    },

    addAnswer() {
      this.editedQuestion.answers.push({
        id: null,
        answer: "",
        is_correct: this.correctAnswerIndex,
        question_id: this.editedQuestion.id,
        category_id: this.category_id,
        aukstructure_id: this.aukstructure_id,
      });
      console.log(this.correctAnswerIndex, "this.correctAnswerIndex")
      this.saveChangesButtons = true
    },
    removeAnswer(index) {
      this.editedQuestion.answers.splice(index, 1);
      this.toggleRemoveButtons()
      this.saveChangesButtons = true
    },
    alertFalse() {
      this.alert = false;
    },
  }
};
</script>






