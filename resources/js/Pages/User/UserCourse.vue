<template>
  <h1 class="h1-title">Страница курса {{ course.course }}</h1>
  <div class="container-course">
    <div class="card-wrap">
      <v-card
          class="mx-auto learning"
          width="344"
      >
        <v-card-item>
          <div>
            <div class="text-overline mb-1">
              Класс: {{ course.className }}
            </div>
            <div class="text-h6 mb-1">
              Категория: {{ course.category }}
            </div>
            <div class="text-caption">Дата: {{ localeDate(course.dateStart) }}</div>
          </div>
        </v-card-item>

        <v-card-actions>
          <v-btn variant="outlined" class="btn-action">
            пройти обучение
          </v-btn>
        </v-card-actions>
      </v-card>
    </div>
    <div v-for="test in testsCourse" :key="test.id" class="card-wrap">
      <v-card class="mx-auto tests-card" width="344">
        <v-card-item>
          <div>
            <div class="text-h6 mb-1">
              Тест: {{ test.title }}
            </div>
            <div class="text-caption">Дата: {{ localeDate(test.dateStart) }}</div>
          </div>
        </v-card-item>

        <v-card-actions>
          <v-btn variant="outlined" class="btn-action">
            пройти тест
          </v-btn>
        </v-card-actions>
      </v-card>
    </div>
  </div>
  <h1 class="h1-title">Оценки за пройденные тесты</h1>
  <v-table class="elevation-1 cursor-pointer course-table">
    <thead>
    <tr>
      <th class="th-table">Попытка</th>
      <th class="th-table">Название теста</th>
      <th class="th-table">оценка</th>
    </tr>
    </thead>
    <tbody>
    <tr
        v-for="test in testScores"
        :key="test.id"
    >
      <td>{{ test.id }}</td>
      <td>{{ test.titleTest }}</td>
      <td>{{ test.score }}</td>
    </tr>
    </tbody>
  </v-table>
  <div class="h1-title" v-if="testScores.length === 0"><h1>Нет пройденных тестов</h1></div>

</template>

<script>
import {mapState} from "vuex";
import localeDate from "../../services/dateFormat.service";

export default {
  name: "UserCourse",
  props: {
    id: {
      type: Number,
      required: true
    }
  },
  computed: {
    ...mapState('UserPage', ['course', 'testsCourse', 'testScores'])
  },
  methods: {
    localeDate: localeDate
  },
  data() {
    return {}
  }
}
</script>

<style>
@import "../../../css/app.css";

.btn-lesson {
  background-color: #B0E0E6;
}

.container-course {
  margin: 10px auto !important;
  text-align: center;
}

.btn-action {
  margin: 0 auto;
  background-color: #90EE90;
}

.learning {
  background-color: rgb(226, 256, 255);
}

.learning, .tests-card {
  margin: 10px !important;
  min-height: 200px;
  text-align: left;

}

.tests-card {
  background-color: #c3e6cb;
}

.btn-action {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 20px;
}

.card-wrap {
  display: inline-block
}
</style>