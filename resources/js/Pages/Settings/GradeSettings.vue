<template>
  <div>
    <table>
      <thead>{{ newValue }}
        <tr>
          <th>Оценка</th>
          <th>Граница</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(grade, index) in gradeBoundaries" :key="index">
          <td>{{ grade.grade }}</td>
          <td>
            <input type="number" v-model="grade.boundary" @change="updateGrade(index)" />
          </td>

        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import $api from "../../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;
export default {
  data() {
    return {
      // gradeBoundaries: [
      //   { boundary: 0, grade: '2' },
      //   { boundary: 35, grade: '3' },
      //   { boundary: 65, grade: '4' },
      //   { boundary: 85, grade: '5' }
      // ],
      gradeBoundaries: []
    };
  },
  mounted() {
    $api.get(apiUrl + '/api/grade-boundary')
      .then(response => {
        this.gradeBoundaries = response.data;
      })
      .catch(error => {
        console.log(error);
      });


    // получение списка вопросов с сервера

  },
  methods: {
    updateGrade(index) {
      const currentGrade = this.gradeBoundaries[index];
      const previousGrade = this.gradeBoundaries[index - 1];
      const nextGrade = this.gradeBoundaries[index + 1];

      if (index === 0) {
        // Для первого элемента граница не должна быть меньше 0
        currentGrade.boundary = Math.max(currentGrade.boundary, 0);
      } else if (index === this.gradeBoundaries.length - 1) {
        // Для последнего элемента граница не должна быть больше 100
        currentGrade.boundary = Math.min(currentGrade.boundary, 100);
      } else {
        // Проверка, чтобы текущая граница не была больше следующей границы и не меньше предыдущей границы
        currentGrade.boundary = Math.max(previousGrade.boundary, Math.min(currentGrade.boundary, nextGrade.boundary));
      }

      const newValue = this.gradeBoundaries[index].boundary;
      // Отправка значения на сервер с помощью Axios
      $api.post('/api/grade-boundary', { index, value: newValue })
        .then(response => {
          console.log(response.data);
        })
        .catch(error => {
          // Обработка ошибки при отправке запроса
          console.error(error);
        });
    }


  }
};
</script>
