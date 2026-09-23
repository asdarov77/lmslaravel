

<template>  
  <h2>Редактирование вопроса {{ idEdit }}</h2>
  <v-form @submit="saveChanges">   
    <v-text-field v-model="editedQuestion.question_text" label="Текст вопроса" @input="checkChanges"></v-text-field>
    <v-row v-for="(answer, index) in editedQuestion.answers" :key="index">
      <v-col cols="1">                      
        <v-radio-group v-model="correctAnswerIndex">
          <v-radio :label="'Ответ ' + (index + 1)" :value="index" @input="checkChanges"></v-radio>
        </v-radio-group>
      </v-col>
      <v-col cols="11">         
        <v-text-field v-model="editedQuestion.answers[index].answer" label="Текст ответа" @input="checkChanges">
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
</template>

<script>
import $api from "../../api/httpClient";
import popup from "../Popup.vue";
export default {
  components: {
    popup
  },
  props:
      {
        idEdit: {
          type: Number,
          required: true
        },
      },
  data() {
    
    return {      
      editedQuestion: {
      question_text: "",
      answers: []
    },
    correctAnswerIndex: null,
    showRemoveButtons: false,
    saveChangesButtons:false,
    
    //---- for popup
    isLoading: false,
      alert: false,
      alertType: "",
      overlay: false,
      snackbarText: "",
      //    
    };
  },
  // computed: {
  //   shuffledAnswers() {
  //     return this.shuffleArray(this.editedQuestion.answers);
  //   }
  // },
  created() {      
      
      $api.get('/api/questions', {
        params: {
           id: this.idEdit,                   
        }
      })
        .then(response => {          
          this.editedQuestion = response.data[0]; 
          //this.shuffleArray(this.editedQuestion.answers); // Перемешиваем варианты ответов
          //this.correctAnswerIndex = this.editedQuestion.answers.findIndex(answer => answer.is_correct);
          this.correctAnswerIndex = this.editedQuestion.answers.findIndex(answer => answer.is_correct); //вариант без перемешивания
          //возвращается массив данных [ { ... } ], а не просто объект { ... }. Поэтому, чтобы присвоить данные из массива response.data переменной editedQuestion, вы должны использовать response.data[0], чтобы получить первый элемент массива.
          //console.log(this.editedQuestion.question_text)
        })
        .catch(error => {
          console.log(error);
        });          
  },

  methods: {
    ///////////////////////////////////////////////////////////////////////////////////
    //   перемешивание вариантов ответов использовать будем при тестировании     //////   
    ///////////////////////////////////////////////////////////////////////////////////

    // shuffleArray(array) {
    //   let currentIndex = array.length;
    //   let temporaryValue, randomIndex;

    //   while (currentIndex !== 0) {
    //     randomIndex = Math.floor(Math.random() * currentIndex);
    //     currentIndex -= 1;

    //     temporaryValue = array[currentIndex];
    //     array[currentIndex] = array[randomIndex];
    //     array[randomIndex] = temporaryValue;
    //   }

    //   return array;
    // },
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
      this.editedQuestion.answers.forEach((answer, index) => {
        answer.is_correct = this.correctAnswerIndex === index;
      });
      // Отправка данных на сервер
      //$api.post('/api/questions', this.editedQuestion)
      $api.patch(`/api/questions/${this.editedQuestion.id}`, this.editedQuestion)
        .then(response => {
          console.log(response);
        })
        .catch(error => {
          console.log(error);
        });
        this.saveChangesButtons = false
    },
    addAnswer() {
      this.editedQuestion.answers.push({
        id: null,
        answer: "",
        is_correct: false,
        question_id: this.editedQuestion.id,
        created_at: null,
        updated_at: null
      });
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


