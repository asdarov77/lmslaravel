

<!-- <template>
  <h2>Редактирование вопроса {{ idEdit }}</h2>{{ editedQuestion }}--корректный--{{ correctAnswerIndex }}
  <v-form @submit="saveChanges" ref="form">
    <v-text-field v-model="editedQuestion.question_text" label="Текст вопроса" @input="checkChanges"
      :rules="[value => value.length >= 10 || 'Текст не менее 10 символов']"></v-text-field>
    <v-row v-for="(answer, index) in editedQuestion.answers" :key="index">
      <v-col cols="1">
        <v-radio-group v-model="correctAnswerIndex">
          <v-radio :label="'Ответ ' + (index + 1)" :value="index" @input="checkChanges()"></v-radio>
        </v-radio-group>
      </v-col>
      <v-col cols="11">
        <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="checkChanges()"
          :rules="[value => validateAnswers(index)]">
          <template v-slot:append>
            <v-btn v-if="showRemoveButtons" @click="removeAnswer(index)" icon color="error">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </template>
        </v-text-field>
      </v-col>
    </v-row>
    <v-btn @click="addAnswer" class="mr-2" color="primary">Добавить ответ</v-btn>
    <v-btn @click="toggleRemoveButtons" class="mr-2" color="error">Удалить ответ</v-btn>
    <v-btn v-if="saveChangesButtons" @click="saveChanges" class="mr-2" color="primary">Сохранить изменения</v-btn>
  </v-form>
  <popup :alert="alert" :alertType="alertType" :snackbarText="snackbarText" :overlay="alert" :alertFalse="alertFalse">
  </popup>
</template> -->



<template >
  <!-- {{ editedQuestion.answers.length }} ------ -->
  <v-card elevation="4" title="Редактирование вопроса">

    <!-- {{ editedQuestion }} --- {{ correctAnswerIndex }} --длина ответов ---- {{ editedQuestion.answers.length }} -->

    <v-form @submit="saveChanges" ref="form">
      <v-row class="ml-3 mr-3">
        <v-col cols="12">
          <v-text-field v-model="editedQuestion.question_text" label="Текст вопроса" @input="checkChanges"
            :rules="[value => value.length >= 10 || 'Текст не менее 10 символов']">
          </v-text-field>
        </v-col>
      </v-row>
      <v-row v-for="(answer, index) in      editedQuestion.answers     " :key="index" class="ml-3 mr-3">
        <v-col cols="1">

          <!-- <v-radio-group v-model="editedQuestion.correctAnswerIndex">
            <v-radio :label="'Ответ ' + (index + 1)" :value="index" @input="checkChanges()"
              :rules="[value => value.length >= 2 || 'Текст не менее 2 символов']">
            </v-radio>
          </v-radio-group> -->


          <v-radio-group v-model="editedQuestion.correctAnswerIndex">
            <v-radio :label="'Ответ ' + (index + 1)" :value="index" @input="checkChanges()"
              :rules="[value => value.length >= 2 || 'Текст не менее 2 символов']">
            </v-radio>{{ index.length }}
          </v-radio-group>


        </v-col>

        <!-- ответы -->

        <!-- <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="checkChanges()" -->

        <!-- <v-col cols="11"> {{ editedQuestion.answers[index].answer.length }}

          <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="updateCorrectAnswer()"
            :rules="[value => editedQuestion.answers.length > 1 || 'Не менее двух вопросов ']">
            <template v-slot:append>
              <v-btn v-if="showRemoveButtons" @click="removeAnswer(index)" icon color="error">
                <v-icon>mdi-close</v-icon>
              </v-btn>
            </template>
          </v-text-field>
        </v-col> -->

        <!-- {{ editedQuestion.answers[index].answer.length }} -->

        <v-col cols="11">

          <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="updateCorrectAnswer()"
            :rules="[
              value => editedQuestion.answers.length > 1 || 'Не менее двух вопросов ',
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
//:rules="[value => validateAnswers(index)]"
// @input="checkChanges"
import $api from "../../api/httpClient";
import popup from "../Popup.vue";
import { required, minLength, minValue } from 'vuelidate/lib/validators'
//import Vuelidate from 'vuelidate'
import { useField, useForm } from 'vee-validate'
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
      isFormCorrect: false,
      //         
    };
  },

  validators: {
    questionText: {
      required,
      minLength: minLength(10)
    },
    answers: {
      required,
      minLength: minLength(2),
      minValue: minValue(2)
    },
    //--------------- было -----------------
    // correctAnswer: {
    //   required
    // },
    //--------------- было -----------------
    correctAnswerIndex: {
      required
    }
  },
  computed: {
    // validatedQuestion() {
    //   return this.$v.editedQuestion.$model
    // }
  },

  created() {
    //console.log(this.category_id, "cat")
    //console.log(this.aukstructure_id, "auk")

  },

  methods: {
    ///////////////////////////////////////////////////////////////////////////////////
    //   перемешивание вариантов ответов использовать будем при тестировании     //////   
    ///////////////////////////////////////////////////////////////////////////////////
    // validate() {




    //   // this.$refs.form.validate()
    //   //   .then(isValid => {
    //   //     console.log(isValid.valid, "isValid")
    //   //   })


    //   //console.log(this.$refs.form.validate(), "this.$refs.form.validate()")
    //   // if (isValid) {
    //   //   // Form is valid, submit data        
    //   //   console.log("valid")
    //   // } else {
    //   //   // Form is invalid, show errors
    //   //   console.log("no   valid")
    //   // }
    // },
    validateAnswers(index) {
      let valid = this.editedQuestion.answers.length >= 2
      console.log(valid, "valid")

      // Проверьте, есть ли положительный ответ 
      let correct = this.editedQuestion.answers.some(a => a.is_correct)
      console.log(correct, "correct")
      //if (valid && correct) {
      if (valid && correct) {
        return true
      } else {
        return 'Должно быть не менее 2 ответов, один из них должен быть правильным'
      }
    },

    updateCorrectAnswer() {
      this.editedQuestion.answers.forEach((answer, index) => {
        answer.is_correct = index === this.correctAnswerIndex
      })
      console.log(this.correctAnswerIndex, 'this.correctAnswerIndex')
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
      // this.editedQuestion.answers.forEach((answer, index) => {
      //   answer.is_correct = this.correctAnswerIndex === index;
      // });

      //this.validate()

      this.$refs.form.validate()
        .then(isValid => {
          //let validation = this.validateAnswers(index)
          console.log(isValid, "isValid")
          if (isValid.valid) {
            //if (validation) {
            console.log("форма валидна")
            // $api.post(`/api/questions/`, this.editedQuestion)
            //   .then(response => {
            //     console.log(response);
            //   })
            //   .catch(error => {
            //     console.log(error);
            //   });
            // this.saveChangesButtons = false

          }
          else {
            console.log("форма не валидна")
          }
        })

      // Отправка данных на сервер
      // const isValid = this.$v.editedQuestion.$model
      //console.log(this.$v.$touch(), "this.$v.$touch()")
      // if (this.validate()) {
      //   console.log(this.validate(), "валидация нет")
      //   return
      // }

      // $api.post(`/api/questions/`, this.editedQuestion)
      //   .then(response => {
      //     console.log(response);
      //   })
      //   .catch(error => {
      //     console.log(error);
      //   });
      // this.saveChangesButtons = false
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
      if (this.correctAnswerIndex !== index) { // проверка что удаляемый ответ не является верным      
        this.editedQuestion.answers.splice(index, 1);
        this.toggleRemoveButtons()
        this.saveChangesButtons = true
      }
      this.snackbarText = "Нельзя удалить правильный ответ";
      this.alertType = "error";
      this.alert = true;
      return;
    },
    alertFalse() {
      this.alert = false;
    },
  }
};
</script>






