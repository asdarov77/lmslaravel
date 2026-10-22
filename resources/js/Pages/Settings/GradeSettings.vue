<template>
  <!--
    Заголовок страницы добавлен: раньше его не было вовсе, страница
    начиналась с таблицы — имени у неё не было ни для человека, ни для
    навигации по заголовкам (WCAG 2.2 SC 1.3.1, 2.4.6).
  -->
  <div class="u-page">
    <PageHeader :title="$t('settings.grades.title')" />

    <!--
      caption обязателен: без него скринридер не может озвучить
      таблицу как «Таблица: название».
    -->
    <table>
      <caption class="u-sr-only">{{ $t('settings.grades.title') }}</caption>
      <!--
        Внутри <thead> раньше стояло {{ newValue }} — отладочный вывод
        переменной, которой в data() нет. Пустой интерполяционный узел
        попадал в заголовок таблицы и попадал в accessibility-дерево.
      -->
      <thead>
        <tr>
          <th>{{ $t('gradeSettings.grade') }}</th>
          <th>{{ $t('gradeSettings.boundary') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(grade, index) in gradeBoundaries" :key="index">
          <td>{{ grade.grade }}</td>
          <td>
            <!--
              aria-label обязателен: ячейка таблицы НЕ связывается с полем
              внутри неё, поэтому без подписи каждое из четырёх полей
              читалось как «поле ввода» — непонятно, границу какой оценки
              человек сейчас правит (WCAG 2.2 SC 1.3.1, 4.1.2).
            -->
            <input
              type="number"
              v-model="grade.boundary"
              :aria-label="`${$t('gradeSettings.boundary')} — ${$t('gradeSettings.grade')} ${grade.grade}`"
              @change="updateGrade(index)"
            />
          </td>

        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import $api from "../../api/httpClient";
import { unwrapArray } from "../../api/envelope";
import PageHeader from "../../components/ui/PageHeader.vue";
const apiUrl = import.meta.env.VITE_APP_URL;
export default {
  components: { PageHeader },
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
        this.gradeBoundaries = unwrapArray(response);
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
