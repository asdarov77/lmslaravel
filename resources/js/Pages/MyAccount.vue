<!-- <template>
  <div class="about">
    <div class="hero is-info">
      <div class="hero-body has-text-centered">        
          <p v-if="loggedIn">
            Добрый день, {{ user.fio }}
          </p>
      </div>
    </div>
  </div>{{totalUsers}}-{{totalGroups}}-{{asd}}
</template> -->

<!-- <template>
  <v-card
    class="mx-auto"
    max-width="500">
    <v-container>
      <v-row dense>
        <v-col cols="12">
          <v-card
            color="#385F73"
            theme="dark"
          >
            <v-card-title class="text-h5">
              Всего пользователей:
            </v-card-title>            
             <v-card-text class="text-center">{{totalUsers}} </v-card-text>         

            <v-card-actions>
              <v-btn variant="text">
                Редактировать
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>

        <v-col cols="12">
          <v-card
            color="#1F7087"
            theme="dark"
          >
            <div class="d-flex flex-no-wrap justify-space-between">
              <div>
                <v-card-title class="text-h5">
                  Всего групп
                </v-card-title>
               <v-card-subtitle class="text-center">{{totalGroups}}</v-card-subtitle>
              </div>

              <v-avatar
                class="ma-3"
                size="125"
                rounded="0"
              >                
              </v-avatar>
              
            </div>
          </v-card>
          
        </v-col>

        <v-col cols="12">
          <v-card
            color="#952175"
            theme="dark"
          >
            <div class="d-flex flex-no-wrap justify-space-between">
              <div>
                <v-card-title class="text-h5">
                  Всего разрешений
                </v-card-title>

                <v-card-subtitle>{{allPermissions.length}}</v-card-subtitle>
  
              </div>

              <v-avatar
                class="ma-3"
                size="125"
                rounded="0"
              >              
              </v-avatar>
            </div>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
</v-card>
</template> -->

<!-- <template>
  <v-card flat class="ma-3 text-xs-center">
  <v-img src="https://cdn.vuetifyjs.com/images/cards/desert.jpg" aspect- 
ratio="2.75"></v-img>
    <v-card-title primary-title>
      <div>
        <h3 class="headline pink--text text--accent-2"> 
asdllkjsad</h3>
        <div>laksjdlaksjd</div>
      </div>
    </v-card-title>
    <v-card-actions>
      <v-btn flat color="orange">Add to Cart</v-btn>
    </v-card-actions>
  </v-card>
</template> -->

<!-- <template>
  <div style="display: flex">
    <v-card v-for="el in elements" style="margin-left: 50px" :key="el.id">
      
      <v-card-title primary-title>{{ el.title }} </v-card-title>
      <v-img :src="el.src" height="150px"> </v-img>
      <v-card-subtitle class="text-center">{{ el.data }}</v-card-subtitle>
    </v-card>
  </div>{{totalGroups}}-{{totalUsers}}
</template> -->

<template>
  <v-container>
  <v-row>
    <v-card
    color="#1F7087"
    theme="dark"
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          Всего пользователей
        </div>
        <div class="text-caption">{{totalUsers}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Добавить
      </v-btn>
    </v-card-actions>
  </v-card>
  <v-card
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          Всего групп
        </div>
        <div class="text-caption">{{totalGroups}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Button
      </v-btn>
    </v-card-actions>
  </v-card>
  <v-card
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          Всего разрешений
        </div>
        <div class="text-caption">{{allPermissions.length}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Button
      </v-btn>
    </v-card-actions>
  </v-card>
</v-row>
</v-container>


<v-container>
  <v-row>
    <v-card
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          Всего курсов
        </div>
        <div class="text-caption">{{courses.length}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Button
      </v-btn>
    </v-card-actions>
  </v-card>
  <v-card
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          Всего категорий
        </div>
        <div class="text-caption">{{categories.length}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Button
      </v-btn>
    </v-card-actions>
  </v-card>
  <v-card
    class="mx-auto"
    width="300"
    variant="outlined"
  >
    <v-card-item>
      <div>
        <div class="text-overline mb-1">
          OVERLINE
        </div>
        <div class="text-h6 mb-1">
          еще что-нибудь
        </div>
        <div class="text-caption">{{allPermissions.length}}</div>
      </div>
    </v-card-item>

    <v-card-actions>
      <v-btn variant="outlined">
        Button
      </v-btn>
    </v-card-actions>
  </v-card>
</v-row>
</v-container>
<td class="py-1">
          <ul>
            <li v-for="item in courses" :key="item.id">
              {{ item.title }}
            </li>
          </ul>
        </td>

</template>

<script>
import { mapState, mapGetters } from "vuex";
export default {
  data: () => ({
    // elements: [
    //   {
    //     title: "всего пользователей",
    //     src: "https://cdn.vuetifyjs.com/images/cards/house.jpg",
    //     flex: 12,
    //     data: '_totalUsers',
    //   },
    //   {
    //     title: "всего групп",
    //     src: "https://cdn.vuetifyjs.com/images/cards/road.jpg",
    //     flex: 12,
    //     data: "totalGroups",
    //   },
    //   {
    //     title: "всего прав",
    //     src: "https://cdn.vuetifyjs.com/images/cards/plane.jpg",
    //     flex: 12,
    //     data: "totalGroups",
    //   },
    //   {
    //     title: "всего курсов",
    //     src: "https://cdn.vuetifyjs.com/images/cards/plane.jpg",
    //     flex: 12,
    //     data: "totalGroups",
    //   },
    // ],
  }),

  components: {},
  async mounted() {
    this.$store.dispatch("User/fetchUsers");
    this.$store.dispatch("User/fetchGroups");
    this.$store.dispatch("User/fetchPermissions");    
    this.$store.dispatch("Course/fetchCourses");    
    this.$store.dispatch("Course/fetchCategories");    
  },

  computed: {
    ...mapState("Auth", ["accessToken", "user"]),
    ...mapState("User", ["totalUsers", "totalGroups", "allPermissions"]),
    ...mapGetters("Auth", ["loggedIn"]),
    ...mapGetters("Course", ["courses"]),
    ...mapState("Course", ["courses","categories"]),
  },
};
</script>