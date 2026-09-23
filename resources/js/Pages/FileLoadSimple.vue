<template>
  <h1>Загрузка файлов</h1>

  <v-file-input
    label="выбери файл"
    @change="onFileSelected"
    show-size
    counter
    multiple
    type="file"
    v-model="image"
    enctype="multipart/form-data"
  >
  </v-file-input>
  <v-btn v-on:click="sendFiles" class="button is-light" type="submit"
    >Отправить
  </v-btn>
</template>


<script>

import $api from '../api/httpClient'; 
export default {
  components: {
    
  },
  data() {
    return {
      image: [],
      selectedFile: null, // текущий выбранный файл
      filesOrder: [], // очередь загрузки      
    };
  },
  methods: {
    onFileSelected(e) {      
      let files = Array.from(e.target.files);
      //console.log(files);
      this.filesOrder = files.slice(); // текущая очередь    

      // this.image = files;
      // for (let item of files) {        
      //   this.sendFiles(item);
      // }
      
    },
    sendFiles() {      
      let formData = new FormData();     

      for( var i = 0; i < this.image.length; i++ ){
          let file = this.image[i];
          formData.append('image[' + i + ']', file);
        }
      
      $api.post("/api/files/add", formData,  // разобраться почему авторизация не работает
          {
            headers: {
                'Content-Type': 'multipart/form-data',                
      //          'Authorization':`Bearer ${localStorage.getItem('token')}`  // вынесен в httpClient.js
            }
          }).then((response) => { this.image = [];          
      });        
    },
  },
};
</script>


