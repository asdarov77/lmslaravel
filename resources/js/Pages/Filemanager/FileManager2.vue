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
      this.subDirectories = response.data;
      
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
       this.template = response.data;
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
        this.subDirectories = response.data.folders;
        this.parent = response.data.fullpath;
        
        //console.log(this.parent, "имя parent");
        //console.log(this.prev_parent, "имя prev_parent");
        //console.log(this.prev_nameLyx,'this.prev_nameLyx');
      });
    },    
  },
};
</script> -->


