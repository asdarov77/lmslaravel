


<template>
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css"
  />
  <v-btn @click="goback"> back</v-btn>
<!--  <v-table dense class="elevation-1 cursor-pointer">-->
<!--    <thead>-->
<!--      <tr>-->
<!--        <th class="text-left">No</th>-->
<!--        <th class="text-left">course</th>-->
<!--      </tr>-->
<!--    </thead>-->

<!--    <tbody>-->
<!--      <tr v-for="(_courses, index) in subDirectories" :key="index">-->
<!--        <td>{{ index }}</td>-->
<!--        <td>-->
<!--          <a @click="showLyx(_courses)">{{ _courses }} </a>-->
<!--        </td>-->
<!--      </tr>-->
<!--    </tbody>-->
<!--  </v-table>-->
  <v-btn @click="test()">Test</v-btn>
  <!-- <p>
    <a :href="'file://' + parent" target="_blank">
      {{ parent }}
    </a>
  </p> -->
</template>

<script>

import axios from "axios";

export default {
  name: 'filemanager',
  data() {
    return {
      subDirectories: [],
      parent: "",
      prev_parent: "",
      nameLyx: "",
      prev_nameLyx: "",
      //titles: [],
    };
  },

  async mounted() {
    // console.log('mounted')
    axios.get("api/tree").then((response) => {
      // console.log(response.data.course_root);
      // console.log(response.data.subfolders);
      this.subDirectories = response.data.subfolders;
      this.parent = response.data.course_root;
    });
  },
  methods: {
    test() {
      //var URL = "file:///home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html";
      var URL = "/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html";
      window.open(URL, null);
    },
    showLyx(nameLyx) {
      if (nameLyx === "index.html") {
        //console.log('nameLyx');
        //console.log(nameLyx, "nameLyx");
        //console.log(this.parent, "parent");
        const url = this.parent + "/" + nameLyx;        
        const url2 = 'http://127.0.0.1:8887'+ url;
        window.open(url2, '_blank');
        //window.open(url, '_blank');
        //window.open('/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html', '_blank');
        //window.location.href = '/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html';
        //location.replace('/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html');
        //document.open(url);
        //setTimeout(() => window.open('/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html'), 1000);

        //         let request = new XMLHttpRequest();
        // request.open("GET", "/home/prynik917/repo/курсы/courses_data/Ил-76/АУК-01/index.html", true);
        // request.onload = () => {console.log(request.responseText)}
        // request.send();

        return;
      }
      const formData = {
        nameLyx: nameLyx,
        parent: this.parent,
      };
      //console.log(nameLyx, "имя папки курса");
      //console.log(formData, "formData");

      this.prev_parent = this.parent;
      
      axios.post("api/tree/list", formData).then((response) => {
        
        //console.log(response.data, "ответ ");
        //this.prev_parent = this.parent;
        this.prev_nameLyx = nameLyx;
        this.subDirectories = response.data.folders;
        this.parent = response.data.fullpath;
        
        //console.log(this.parent, "имя parent");
        //console.log(this.prev_parent, "имя prev_parent");
        //console.log(this.prev_nameLyx,'this.prev_nameLyx');
      });
    },
    goback() {
      //console.log(this.prev_nameLyx,'this.prev_nameLyx');
      //this.showLyx(this.prev_nameLyx);
      this.parent = this.prev_parent;
      this.showLyx(this.prev_nameLyx);
      // parent = this.prev_parent;

      // const formData = {
      //   nameLyx: this.prev_nameLyx,
      //   parent: this.prev_parent,
      // };
      // axios.post("api/tree/list", formData).then((response) => {
      //   //axios.get("api/tree/" + nameLyx, formData).then((response) => {
      //   console.log(response.data, "ответ ");
      //   this.prev_parent = response.data.fullpath;
      //   this.subDirectories = response.data.folders;
      //   //this.parent = response.data.fullpath;
      // });

    },    
  },
};
</script>
