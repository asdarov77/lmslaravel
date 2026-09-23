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
      .get(apiUrl+"/api/course/" + this.idEdit)
      .then((response) => {
        const air = response.data.aircraft.path.trim();
        const auk = response.data.path.trim();
        const base = "api/private/" + air + "/" + auk;
        const url = base + "/index.html";

        //console.log(url);
        $api.get(url).then((response) => {
          //       $api.get("api/private/Ми-38/АУК-01/index.html")
          //       //console.log(response.data);
          const baseurl = "<base href=" + base + "/ />";
          //console.log(baseurl);
          this.content = baseurl + response.data;
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


