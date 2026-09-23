<template>
  <h1 class="h1-title">Добро пожаловать, {{ user.fio }}</h1>
  {{ courses }}
  <v-table class="elevation-1 cursor-pointer course-table"  v-for="course in courses" :key="course.id">
    <thead>
    <tr>
      <th  colspan="8" class="th-table">{{courses.title}}</th>
    </tr>
    </thead>
    <tbody v-for="topic in course.topics" :key="course.topicId">
    <tr>
      <td class="topic-name"></td>
      <td colspan="7" class="topic-name">{{topic.topicName}}</td>
    </tr>
    <tr  v-for="chapter in topic.chapters" :key="chapter.chapterId">
      <td class="center-text">{{ formatDate(chapter) }}</td>
      <td>{{chapter.chapterName}}</td>
      <td class="center-text">{{chapter.countHours ? chapter.countHours + 'ч': '-'}}</td>
      <td class="center-text" style="width: 40px"><v-btn class="bg-lblue">Изучить</v-btn></td>
      <td :class="getClassProgress(chapter.progress)">{{chapter.progress ? chapter.progress + '%' : '-'}}</td>
      <td class="center-text" style="width: 40px"><v-btn class="bg-lblue">Пройти тест</v-btn></td>
      <td :class="getClassScore(chapter.score)">{{chapter.score ? chapter.score : '-'}}</td>
      <td class="center-text">{{chapter.correctAnswers ? chapter.correctAnswers + '%' : '-'}}</td>
    </tr>
    </tbody>
  </v-table>
</template>

<script>
import $api from "../../api/httpClient";
import {mapState} from "vuex";

export default {
  name: "UserPage",
  computed: {
    //...mapState('UserPage', ['courses',]),    
    ...mapState('Course', ['group2learnings','courses']),
    ...mapState('Auth', ['user',])
  }, 

  data() {
    return {
    }
  },
  methods: {
    formatDate(date){
      const options = {
        month: "numeric",
        day: "numeric",
      }
      return `${date.dateChapterStart.toLocaleDateString('ru', options)} - ${date.dateChapterStop.toLocaleDateString('ru', options)}`
    },
    getClassProgress(progress){
      console.log(progress,'progress')
      if (progress === 100) return 'green-100 center-text'
      if (progress > 70 && progress < 100) return 'green-70 center-text'
      if (progress > 50 && progress < 70) return 'yellow-50 center-text'
      if (progress && progress < 50) return 'red-50 center-text'
      else return 'center-text'
    },
    getClassScore(score){
      if (score === 5) return 'green-100 center-text'
      if (score === 4) return 'green-70 center-text'
      if (score === 3) return 'yellow-50 center-text'
      if (score === 2) return 'red-50 center-text'
      else return 'center-text'
    },



    localeDate(date) {
      {
        return new Date(date).toLocaleString('ru',{
          hour: "numeric",
          minute: "numeric",
          year: "numeric",
          month: "long",
          day: "numeric",
        })
      }
    },
    nextLessonCalculate() {
      if (this.courses.length > 0) {
        const tempCourses = Array.from(this.courses)
        const next = tempCourses.sort((a, b) => b.dateStart.getTime() - a.dateStart.getTime())[0]
        const day = next.dateStart.toLocaleDateString()
        const hours = next.dateStart.getHours()
        const minutes = next.dateStart.getMinutes()
        return `Ближайшее занятие состоится ${day} в ${hours} часов ${minutes} минут`
      } else {
        return 'Нет предстоящих занятий'
      }
    }
  }
}
</script>

<style>
@import "../../../css/app.css";
.center-text {
  text-align: center;
}
.bg-lblue {
  background-color: #a4ccff;
}
.green-100 {
  background-color: #50c950;
}
.green-70 {
  background-color: rgba(143, 215, 139, 0.94);
}
.yellow-50 {
  background-color: rgba(236, 236, 155, 0.87);
}
.red-50 {
  background-color: rgba(236, 201, 155, 0.87);
}
</style>