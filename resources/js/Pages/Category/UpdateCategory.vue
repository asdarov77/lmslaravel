<template>
  <v-card>
    <v-card-title>Редактирование категории</v-card-title>
      <!--
        Пока категория не приехала из store, category === null. Обращаться
        к category.title в таком состоянии нельзя: v-model падал с
        "Cannot read properties of null" ещё до загрузки данных.
        Форма рендерится только когда сущность готова к правке.
      -->
      <v-row v-if="category">
        <v-col>
        <v-text-field
            label="Название категории"
            type="text"
            v-model="category.title"
        ></v-text-field>
        </v-col>
        <v-col>
          <v-text-field
              label="Краткое название курса"
              type="text"
              v-model="category.description"
          ></v-text-field>
        </v-col>
      </v-row>
      <v-progress-circular v-else indeterminate></v-progress-circular>
      <v-container class="notification is-danger" v-if="errors.length">
        <p v-for="error in errors" v-bind:key="error">
          {{ error }}
        </p>
      </v-container>
<!--    <v-card-actions>-->
<!--      <v-spacer></v-spacer>-->
<!--      <v-btn @click="this.$router.back()" color="error">Отмена</v-btn>-->
<!--      &lt;!&ndash; <v-btn v-on:click="login" color="primary" to="/MyAccount">Login</v-btn> &ndash;&gt;-->
<!--      <v-btn @click="submitForm()" color="primary">Сохранить</v-btn>-->
<!--    </v-card-actions>-->
    <ButtonGroup @submitForm="submitForm" v-if="category"></ButtonGroup>
  </v-card>
</template>

<script>
// FIXME !! title=character(255), description=text. Разница в курсоре при клике на поле в форме редактирования
import {mapState, mapGetters} from 'vuex'
import ButtonGroup from "../../components/ButtonGroup.vue";

export default {
  components: {
    ButtonGroup
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
      errors: [],
    };
  },
  async mounted() {
    //console.log("mounted",this.catId, "эта категория");    
    this.$store.dispatch('Course/fetchCategory', this.idEdit)
  },
  computed: {
    ...mapState('Course', ['courses', 'category', 'totalCategories']),
    ...mapGetters('Course', ['categories', 'courses']),
  },

  methods: {
    submitForm() {
      // Без этой проверки кнопка «Сохранить» отправляла бы null вместо
      // данных категории (v-model уже не даёт нажать её, но действие
      // можно вызвать и программно).
      if (!this.category) {
        return Promise.resolve()
      }

      // Отправляем только редактируемые поля. Раньше уходил весь объект
      // state.category вместе с appended-алиасом name и служебными полями
      // (id/created_at/updated_at), из-за чего правка названия терялась.
      const data = {
        title: this.category.title,
        description: this.category.description,
      }

      return this.$store
        .dispatch('Course/updateCategory', { id: this.idEdit, data })
        .then(() => {
          this.errors = []
          this.$router.back()
        })
        .catch(error => {
          this.errors = []
          const response = error && error.response
          const validation =
            response && response.data && response.data.errors
              ? response.data.errors
              : null
          if (validation) {
            Object.keys(validation).forEach(field => {
              this.errors.push(`${field}: ${validation[field][0]}`)
            })
          } else {
            this.errors.push('Не удалось сохранить категорию')
          }
        })
    },
  },
};
</script>

<style></style>