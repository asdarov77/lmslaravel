<template>
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"
  />
  <div class="courses">
    <div class="hero is-info">
      <div class="hero-body has-text-centered">
        <h1 class="title">Автоматизированные учебные курсы</h1>
      </div>
    </div>
    <section class="section">
      <div class="container">
        <div class="columns">
          <div class="column is-2">
            <aside class="menu">
              <p class="menu-label">Классы</p>
              <v-chip
                @click="clickair(tag.id)"
                v-for="tag in tags"
                :key="tag.id"
                
                class="xxx ml-1 mr-1"
                :selected="selected"               
              >
                {{ tag.path }}
              </v-chip>
            </aside>
            <v-divider></v-divider>
            <aside class="menu">
              <p class="menu-label">Категории курсов</p>
              <ul class="menu-list">
                <li >
                  <a 
                    v-bind:class="{ 'is-active': !activeCategory }"
                    @click="setActiveCategory({ id: 0 })"
                    >{{ activeCategory.id }}/{{ aircraft_id }}
                    Все категории
                  </a>
                </li>
                <li
                  v-for="_category in categories"
                  v-bind:key="_category.id"
                  @click="setActiveCategory(_category)"
                >
                  <a> {{ _category.title }} </a>
                </li>
              </ul>
              <v-switch
                v-model="show"
                hide-details
                :label="`Показать: ${show.toString()}`"
                @change="getCourses(this.show)"
              ></v-switch>
            </aside>
          </div>
          <div class="column is-10">
            <div class="columns is-multiline">
              <div
                class="column is-4"
                v-for="course in courses.filter(
                  (course) => course.visible === this.show
                )"
                v-bind:key="course.id"
              >
                <div class="card">
                  <div class="card-image">
                    <figure class="image is-4by3">
                      <img
                        src="http://bulma.io/images/placeholders/640x480.png"
                        alt="Placeholder image"
                      />
                    </figure>
                  </div>
                  <div class="card-content">
                    <div class="media">
                      <div class="media-content">
                        <p class="is-size-5">{{ course.title }}</p>
                      </div>
                    </div>
                    <div class="content">
                      <p>{{ course.short_description }}</p>
                      <v-row align="center" justify="space-around">
                        <v-btn size="small"
                          color="primary"
                          flat
                          :to="{
                            name: 'courses.desc',
                            params: { idEdit: course.id },
                          }"
                        >
                          Больше
                        </v-btn>
                        <v-btn size="small"
                          color="blue-grey"
                          flat
                          :to="{
                            name: 'course.update',
                            params: {
                              idEdit: course.id,
                            },
                          }"
                          >Редактировать</v-btn
                        >
                        <v-btn size="small"
                          color="error"
                          flat
                          @click="deleteCourse(course.id)"
                          >Удалить</v-btn
                        >
                        <v-btn size="small"
                          color="error"
                          flat
                          :to="{
                            name: 'courses.itemmani',
                            params: { idEdit: course.id },
                          }"  target="_blank"
                          >Открыть</v-btn
                        >
                        <!-- <v-btn
                          color="error"
                          flat
                          :to="{
                            name: 'courses.item',
                            params: { idEdit: course.id },
                          }"  target="_blank"
                          >MF</v-btn
                        > -->                        
                      </v-row>
                    </div>
                  </div>
                </div>
              </div>
              <div class="column is-12">
                <nav class="pagination">
                  <a class="pagination-previous">Предыдущий</a>
                  <a class="pagination-next">Следующий</a>
                  <v-btn
                    color="secondary"
                    class="pagination-link is centred"
                    :to="{ name: 'course.store' }"
                    >Добавить курс</v-btn
                  >
                  <ul class="pagination-list">
                    <li>
                      <a class="pagination-link is-current">1</a>
                    </li>
                    <li>
                      <a class="pagination-link">2</a>
                    </li>
                    <li>
                      <a class="pagination-link">3</a>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
<script>
//@click="openUrl(course.id)"
import $api from "../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;
import { mapState, mapGetters } from "vuex";
export default {
  data() {
    return {
      //courseId: null,
      activeCategory: {
        id: 0,
      },
      show: true,
      url: "", // ??
      aircraft_id: 0,
      tags: [],

      selected:false,
    };
  },
  async mounted() {
    //console.log('mounted')
    this.$store.dispatch("Course/fetchCourses");
    this.$store.dispatch("Course/fetchCategories");
    this.getCourses(this.show); // временный вариант для скрытия курсов
    // --------------загрузка классов----------------
    $api.get(apiUrl+"/api/classes").then((response) => {
      //console.log(response.data, "v-chip");
      this.tags = response.data;
    });
    // --------------загрузка классов----------------
  },
  computed: {
    ...mapState("Course", ["course", "category", "totalCourses"]),
    ...mapGetters("Course", ["categories", "courses"]),
  },
  methods: {
    setActiveCategory(_category) {
      //console.log(_category.id, "активная категория");
      this.activeCategory = _category;
      //console.log(this.activeCategory)
      this.getCourses(this.show);
    },
    //getCourses(isVis) {
    //-----------------------------------------
    // getCourses() {
    //   if (this.activeCategory) {
    //     this.$store.dispatch("Course/fetchCoursecat", this.activeCategory.id);
    //   } else this.$store.dispatch("Course/fetchCourses");
    // },
    //-----------------------------------------

    getCourses() {
      if (this.activeCategory) {
        //console.log(this.activeCategory, "this.activeCategory");
        this.$store.dispatch("Course/fetchCoursesFilter", {
          params: {
            category_id: this.activeCategory.id,
            aircraft_id: this.item,
          },
        });
      } else this.$store.dispatch("Course/fetchCourses");
    },
    async deleteCourse(course_id) {
      //console.log(course_id, 'текущий курс');
      this.$store
        .dispatch("Course/deleteCourse", course_id)
        .catch((error) => console.error(error));
      this.$store
        .dispatch("Course/fetchCourses")
        .catch((error) => console.error(error))
        .finally(() => console.log("удален курс  ", course_id));
    },
    clickair(item_id) {
      this.selected = !this.selected
      //console.log(item_id)
      if (item_id == this.aircraft_id) {
        this.aircraft_id = 0;
      } else {
        this.aircraft_id = item_id;
      }
      //console.log(this.aircraft_id, "this.aircraft_id")
      this.$store
        .dispatch("Course/fetchCoursesFilter", {
          params: {
            category_id: this.activeCategory.id,
            aircraft_id: this.aircraft_id,
          },
        })
        .catch((error) => console.error(error));
    },
          
      //window.open("api/private/Ми-38/АУК-01/index.html", '_blank');
      
      // var params =
      //  {
      //    'Authorization': 'Bearer ' + '32|OtMVKbMv1vDMS3r7X4aXcXxLWtEzwzFxSJlwM9et',
      //    'Content-type': 'application/x-www-form-urlencoded',
      //  };

      // $api.get("api/private/Ми-38/АУК-01/index.html")
      // .then((response) => {
      //   this.content = response.data;
      //   window.open("","_blank");
      // });

      //window.open($api.get("api/private/Ми-38/АУК-01/index.html")), '_blank');
      //$api.get("api/private/Ми-38/АУК-01/index.html")
      //url = "api/private/Ми-38/АУК-01/index.html";
      // const viewFile = async (url) => {
      //  window.open(url, '_blank');
      // // Change this to use your HTTP client
      //     fetch(url, {'Authorization': 'Bearer ' + '32|OtMVKbMv1vDMS3r7X4aXcXxLWtEzwzFxSJlwM9et'} ) // FETCH BLOB FROM IT
      //       .then((response) => response.blob())
      //       .then((blob) => { // RETRIEVE THE BLOB AND CREATE LOCAL URL
      //         var _url = window.URL.createObjectURL(blob);
      //         window.open(_url, "_blank").focus(); // window.open + focus
      //     }).catch((err) => {
      //       console.log(err);
      //     });
      // };
    
  },
};
</script>

<style>
/* .xxx {
  background: none;
} */

.xxx:hover  {
  background: lightgreen;
  font-weight: bold;
}
.xxx:focus {
  /* background: red; */
  background-color: lightgrey;
  font-weight: bold;
}

/* .xxx .v-chip__content {
  color: white;
  font-weight: bold;
} */

.menu-list a:hover  {
  background-color: lightgreen !important;
  font-weight: bold;
}
.menu-list a:focus {
  /* background: red; */

  background-color: blue !important;
  font-weight: bold;
}


</style>
