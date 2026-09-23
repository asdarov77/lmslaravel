<template>
  <h1>
    Менеджер категорий
    <v-btn class="mb-1" outlined :to="{ name: 'categories.store' }">
      + Добавить категорию</v-btn
    >
  </h1>

  <v-container fluid>
    <v-row class="ma-2" justify="center">
      <v-checkbox
        v-for="air in aircrafts"
        :key="air.id"
        @click="filter(air.id)"
        active-class="xxx"
        v-model="checked"        
        :value="air.path"        
      >
        {{ air.path }}
      </v-checkbox>
    </v-row>
  </v-container>

  <v-data-table dense class="elevation-3">
    <template v-slot:default>
      <thead>
        <tr>
          <th class="text-center">id</th>
          <th class="text-center">Наименование</th>
          <th class="text-center">Описание</th>
          <th class="text-center">Редактировать</th>
          <th class="text-center">Удалить</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in filtredCat" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.title }}</td>
          <td>{{ item.description }}</td>
          <td>
            <v-btn
              tile
              color="success"
              class="my-1"
              :to="{
                name: 'categories.update',
                params: {
                  idEdit: item.id,
                },
              }"
            >
              <v-icon left> mdi-pencil</v-icon>
              Редактировать
            </v-btn>
          </td>
          <td>
            <v-btn @click="deleteCat(item.id)" color="error" class="my-1" tile>
              Удалить
            </v-btn>
          </td>
        </tr>
      </tbody>
    </template>
  </v-data-table>
</template>

<script>
import { mapState, mapGetters } from "vuex";
import * as labs from "vuetify/labs/components";

export default {
  components: {
    ...labs,
  },
  name: "CategoryList",
  data() {
    return {
      filtredCat: [],
      checked:false,
    };
  },

  created() {
    this.$store.dispatch("Course/fetchAircrafts");
    this.$store
      .dispatch("Course/fetchCategories")
      .catch((error) => console.error(error));
  },

  computed: {
    ...mapGetters("Course", ["categories", "aircrafts"]),
    ...mapState("Course", ["totalCategories", "categories", "aircrafts"]),
  },

  methods: {
    async deleteCat(cat_id) {
      this.$store
        .dispatch("Course/deleteCategory", cat_id)
        .catch((error) => console.error(error));
      this.$store
        .dispatch("Course/fetchCategories")
        .catch((error) => console.error(error))
        .finally(() => console.log("удалена категория  ", cat_id));
    },
    filter(air_id) {
      this.filtredCat = this.categories.filter(
        (el) => el.aircraft_id === air_id
      );
      //console.log(this.filtredCat, "filter");
    },
  },
};
</script>