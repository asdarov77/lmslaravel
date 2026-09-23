<template>
  <v-flex xs12 sm8 md4>
    <v-card class="elevation-12 mx-auto">
      <v-toolbar color="primary">
        <v-toolbar-title>Добавить категорию </v-toolbar-title>
      </v-toolbar>
      <v-card-text>
        <v-form v-on:@submit.prevent="submitForm">
          <v-text-field
            label="Название категории"
            type="text"
            v-model="title"
          ></v-text-field>
          <v-text-field
            label="Описание категории"
            type="text"
            v-model="description"
          ></v-text-field>
          <v-container class="notification is-danger" v-if="errors.length">
            <!--class="has-text-centered"> -->
            <p v-for="error in errors" v-bind:key="error">
              {{ error }}
            </p>
          </v-container>
        </v-form>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <!-- <v-btn v-on:click="submitForm" color="primary">Сохранить</v-btn> -->
        <ButtonGroup @submitForm="submitForm" @cancelBtn="cancelBtnHead"></ButtonGroup>
      </v-card-actions>
    </v-card>
  </v-flex>
</template>

<script>
import { mapState, mapGetters } from 'vuex'
import ButtonGroup from "../../components/ButtonGroup.vue";

export default {
  components: {ButtonGroup},

  data() {
    return {
      title: "",
      description: "",
      errors: [],
    };
  },

  async mounted() {
    //console.log("mounted register category");    
  },
  computed: {
      ...mapState('Course', ['totalCategories','categories']),
      ...mapGetters('Course', ['categories','courses']),
  },

  methods: {
    submitForm: function () {
      if (!this.errors.length) {
        const formData = {
          title: this.title,
          description: this.description,
        };
        this.$store
          .dispatch("Course/createCategory", { ...formData })
          .then(() => {})
          .catch((error) => {
            console.error(error);
          })
          .finally(() => this.$router.back());
      }
    },
    cancelBtnHead()
    {
      //console.log('cancel');
      this.$router.go(-1);
    },
  },
};
</script>

<style></style>