<template>
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"
  />
  <!-- <div class="courses">
    <div class="hero is-info">
      <div class="hero-body has-text-centered">
        <h1 class="title">Страница курса</h1>
      </div>
    </div>
  </div> -->

  <iframe :srcdoc="content" width="100%"  frameborder="0" style="margin-top:-64px; height: 1024px">
  </iframe>
</template>

<script>
import $api from "../api/httpClient";
import { unwrapResponse } from "../api/envelope";
const apiUrl = import.meta.env.VITE_APP_URL;
// <iframe :src="'../../курсы/courses_data/'+{{course.path}}+'index.html'"> </iframe>
import { mapState, mapGetters } from "vuex";
export default {
  props: {
    idEdit: {
      type: Number,
      required: true,
    },
  },

  data() {
    return {
      show: true,
      content: "",      
    };
  },
  async mounted() {    
    //console.log("mounted", this.idEdit, "этот курс");
    this.$store.dispatch("Course/fetchCourse", this.idEdit);

    // var myIframe = document.querySelector('#myIframe');
    // var myURL= "http://127.0.0.1:8000/api/course/1/";

        $api
        .get(apiUrl + "/api/course/" + this.idEdit)
        .then((response) => {
          const course = unwrapResponse(response);
          const air = (course.aircraft ? course.aircraft.path : "").trim();
          const auk = (course.path || "").trim();

          if (!air || !auk) {
            this.content = "";
            return;
          }

          // Контент курса отдаётся по подписи, а не по auth:sanctum:
          // вложенные ресурсы (CSS/JS/картинки) браузер запрашивает напрямую,
          // без заголовка Authorization. Подпись передаётся в query-строке,
          // поэтому браузер передаёт её сам и стили не ломаются.
          $api
            .get(apiUrl + "/api/private/signed-url", {
              params: { aircraft: air, auk: auk },
            })
            .then((sigResponse) => {
              const signed = unwrapResponse(sigResponse);
              const base = signed.base;

              if (!base) {
                this.content = "";
                return;
              }

              // Файлы курса — необязательный ресурс: их может не быть на диске,
              // поэтому помечаем запрос optional. Иначе общий 404-обработчик
              // httpClient писал ошибку в консоль и уводил на страницу 404.
              $api
                .get(base + "index.html", { optional: true })
                .then((response) => {
                  // <base> с подписью: все относительные ресурсы внутри
                  // index.html наследуют expires/signature из префикса пути.
                  const baseurl = '<base href="' + base + '" />';
                  this.content = baseurl + response.data;
                })
                .catch(() => {
                  // Файлов курса может не быть на диске — раньше отклонение
                  // превращалось в unhandled rejection и роняло страницу.
                  this.content = "";
                });
            })
            .catch(() => {
              this.content = "";
            });
        })
        .catch((err) => alert(err));
  },

  computed: {
    ...mapState("Course", ["course", "category", "totalCourses"]),
    ...mapGetters("Course", ["categories", "courses"]),
  },
};
//{{this.$store.state.Auth.accessToken}}
</script>


