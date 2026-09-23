<!-- данный компонент делает upload и распаковку файла, используется в компоненте AddClass. Пока закомментирован-->
<template>
    <div>
      <input type="file" @change="handleFileChange">
      <v-progress-linear v-if="progress !== null" :value="progress" :height="10" color="primary"></v-progress-linear>
    </div>
  </template>
  
  <script>

  import $api from "../api/httpClient";
  const apiUrl = import.meta.env.VITE_APP_URL;
  export default {
    data() {
      return {
        progress: null
      };
    },
    methods: {
      async handleFileChange(event) {
        const file = event.target.files[0];
        const formData = new FormData();
        formData.append('file', file);
  
        try {
          // Upload the file to the server          
          const response = await $api.post(apiUrl + '/api/upload', formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            },
            onUploadProgress: (progressEvent) => {
              // Update the progress bar as the file is uploaded
              this.progress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
            }
          });
  
          // Extract the contents of the ZIP archive
          
          await $api.post(apiUrl +'/api/extract', {
            //filePath: 'TqFYliSLXKsq6mZ62x44mMqNgTuv6eJiNLbvjMZS.zip'
            filePath: response.data.filePath
           // response.data.filePath
          }, {
            onUploadProgress: (progressEvent) => {
              // Update the progress bar as the archive is extracted
              this.progress = 100 + Math.round((progressEvent.loaded / progressEvent.total) * 100);
            }
          });

          // Reset the progress bar
          this.progress = null;
        } catch (error) {
          console.error(error);
        }
      }
    }
  };
  </script>
  