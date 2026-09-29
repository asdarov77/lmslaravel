<!-- 


<template>  

  <v-btn @click="test()">Test</v-btn>
  <v-btn @click="test2()">Test2</v-btn>
  <v-btn @click="test3()">Test3</v-btn>
  <v-btn @click="test4()">Signed</v-btn>
  <html-import></html-import>
  
  <div v-html="template"></div>

</template>

<script>

import axios from "axios";
import { unwrapResponse, unwrapArray, unwrapField } from "../../api/envelope";
//import htmlImport from '../../../../public/courses_data/1/index.html'

export default {
  data() {
    return {
      subDirectories: [],
      parent: "",      
      curFolder: "",  
      template: [], 
      
      
      
    };
    
  },
  //components:{htmlImport},
  async mounted() {
    
    // console.log('mounted')
    axios.get("api/tree2/").then((response) => {
      // console.log(response.data.course_root);
      // console.log(response.data.subfolders);
      console.log(response.data);
      this.subDirectories = unwrapArray(response);

    }).catch(() => {
      // api/tree2 отсутствует в routes/api.php (404). Без catch() это
      // давало unhandled rejection — показываем пустой список.
      this.subDirectories = [];
    });
  },
  methods: {
    
    test() {      
      //var url = "/courses_data/1/index.html";
      var url = "/courses_data/1/index.html";
      this.openLink(url);
    },
    test2() {      
      var url = "/courses_data/2/index.html";
      this.openLink(url);
    },
    test3() {      
      var url = "/courses_data/3/index.html";
      this.openLink(url);
    },
    test4() {      
      var url = "/courses_data/1/index.html";
      this.openLink(url);
    },
    // openLink(url){
      
    //   const formData = {url: url};
    //   axios.post("secret",formData).then((response) => {
    
    //     //const link = response.config.data;
    //     //console.log(link, 'link');
    //     //window.open(response.data, null);
    //   })
    openLink(url){      
      
      axios.get(url).then(response => {
        console.log(response.data);
       this.template = unwrapResponse(response);
    // resolve({template: response.data})
  })
  },
  
    showSub(curFolder) {

      const formData = {
        curFolder: curFolder,
        parent: this.parent,
      };      
      
      axios.post("api/tree2/list", formData).then((response) => {
        
        console.log(response.data, "ответ ");      
        this.subDirectories = unwrapField(response, 'folders') || [];
        this.parent = unwrapField(response, 'fullpath');
        
        //console.log(this.parent, "имя parent");
        //console.log(this.prev_parent, "имя prev_parent");
        //console.log(this.prev_nameLyx,'this.prev_nameLyx');
      });
    },    
  },
};
</script> -->


