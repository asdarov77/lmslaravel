<template>
  <v-card>
    <v-card-title>Редактирование категории</v-card-title>
      <v-row>
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
    <ButtonGroup @submitForm="submitForm"></ButtonGroup>
  </v-card>
</template>

<script>
// FIXME !! title=character(255), description=text. Разница в курсоре при клике на поле в форме редактирования
import {mapState, mapGetters} from 'vuex'
import ButtonGroup from "../../components/ButtonGroup.vue";
import {updateCategory} from "../../api/course.api"

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
      updateCategory(this.idEdit, this.category)
          .then(() => {
          })
          .catch(error => {
            console.error(error)
          })
          .finally(() => this.$router.back())


        // this.$store
        //     .dispatch('Course/updateCategory', {id: this.idEdit, data: this.category})
        //     .then(() => {
        //     })
        //     .catch(error => {
        //       console.error(error)
        //     })
        //     .finally(() => this.$router.back())
    },
  },
};
</script>

<style></style>