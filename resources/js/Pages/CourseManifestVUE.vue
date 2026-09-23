<template>
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"
  />

  <v-container class="bg-grey">
    <v-row>
      <v-col cols="4">
        <!-- <v-sheet rounded elevation="4" class="flex-child text-subtitle-1  pa-2 mt-1">
          <div v-for="(item, index) in aukstructures" :key="item.parent_id">
            <div 
              class="mt-1 mr-5"
              :style="[
                item.type !== 3
                  ? {
                      cursor: 'default',
                      opacity: '.7',
                      color:'green',
                    }
                  : {
                      cursor: 'pointer',
                      //border: '2px solid firebrick',
                      
                    },
                {
                  fontSize: `${-5 * item.type + 30}px`,
                  transform: `translate(${item.type * 20}px)`,
                  display: 'inline-block',
                  wordWrap: 'break-word'
                },
              ]"
            >
              <div
              
              @mouseover="item.type === 3 ? showthumb(item.id) : ''"
              @mouseleave="hidethumb(item.id)"
              
                :id="item.id"
                @click="item.type === 3 ? getlink(item.id) : ''"
                v-if="index !== 0"                                
                >
               {{ item.title }}
              </div>
            </div>
          </div>
        </v-sheet> -->

        <v-sheet
          rounded
          elevation="4"
          class=" pa-2 mt-1"
        >
          <v-list-item
          class="tile "
          
            v-for="item in aukstructures"
            :key="item.parent_id"
            :title="item.title"
            @click="item.type === 3 ? getlink(item.id) : ''"
            
            :id="item.id"
            @mouseover="item.type === 3 ? showthumb(item.id) : ''"
            @mouseleave="hidethumb(item.id)"

            rounded="l"
            :style="[
                item.type !== 3
                  ? {
                      cursor: 'default',
                      opacity: '.7',
                      color:'green',                      
                    }
                  : {
                      cursor: 'pointer', 
                                      
                    },
                {
                 // fontSize: `${-5 * item.type + 30}px`,
                  transform: `translate(${item.type * 20}px)`,
                //  display: 'inline-block',
                 wordWrap: 'break-word'
                 
                },
              ]"
          >
       


          </v-list-item>
        </v-sheet>
      </v-col>

      <v-col cols="8">
        <v-sheet rounded class="pa-2 mt-1" elevation="4">
          <iframe
            class="hello"
            :src="link"
            width="100%"
            onload="this.style.height=(this.contentWindow.document.body.scrollHeight+20)+'px';"
          >
          </iframe>
        </v-sheet>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import $api from "../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;
import { mapState, mapGetters } from "vuex";
export default {
  props: {
    idEdit: {
      type: Number,
      required: true,
    },
    treeData: Object,
  },

  data() {
    return {
      titleauk: "",
      aukstructures: {},
      categories: {},
      link: "",
    };
  },

  async mounted() {
    this.$store.dispatch("Course/fetchCourse", this.idEdit);

    // загрузка левого меню
    $api
      .get(apiUrl+"/api/course/" + this.idEdit)
      .then((response) => {
        this.titleauk = response.data.title;
        this.aukstructures = response.data.aukstructures;
        this.getlink(this.aukstructures[0].id);
      })
      .catch((err) => alert(err));
  },

  computed: {
    ...mapState("Course", ["course", "category", "totalCourses"]),
    ...mapGetters("Course", ["categories", "courses"]),
    // indent() {
    //   return {fontSize: `${-4 * this.item.type + 30}px`,transform: `translate(${this.item.type * 20}px)`, };
    // },
  },

  methods: {
    showthumb(item_id) {
       //console.log(item_id)
      // document.getElementById(item_id).style.border = "2px doted grey ";
      // document.getElementById(item_id).style.borderRadius = "4px";
      // document.getElementById(item_id).style.background = "#D3D3D3";

      //document.getElementById(item_id).style.fontSize = "150%";
    },
    hidethumb(item_id) {
      //console.log(item_id, 'вышел')
      // document.getElementById(item_id).style.border = "none";
      // document.getElementById(item_id).style.background = "none";
    },
    getlink: function (item_id) {
      $api
        .get(apiUrl+"/api/getlink/" + item_id)
        .then((response) => {
          this.link = response.data;
        });
    },
  },
};
</script>

<style>
  .tile {
    margin: 5px;
    border-radius: 4px;
  }
  .tile:hover  {
    background: green;
  }
  .tile:active {
    background: yellow;
  } 

   .v-list-item-title {
    font-size: 1rem;  
  }
  /* .tile {
  font-size: 1.5rem !important; font-weight:700;
}  */
.custom {
      font-size: 1.5rem;
    }
</style>