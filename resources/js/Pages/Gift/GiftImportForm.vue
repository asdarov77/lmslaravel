<template>
    <div>
      <input type="file" ref="fileInput" @change="onFileChange">
      <div v-if="results.length">
        <h2>Результаты:</h2>
        <ul>
          <li v-for="(result, index) in results" :key="index">
            <h3>{{ result.question }}</h3>
            <p>{{ result.answer }}</p>
          </li>
        </ul>
      </div>
    </div>
  </template>
  
  <script>
  import axios from 'axios';
  
  export default {
    data() {
      return {
        results: []
      };
    },
    methods: {
      async onFileChange() {
        const file = this.$refs.fileInput.files[0];
        // Здесь можно выполнить проверки на тип файла и размер
        await this.uploadFile(file);
      },
      async uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
  
        try {
          const response = await axios.post('/api/gift', formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            }
          });
  
          // Обработка ответа от сервера
          this.results = response.data;
        } catch (error) {
          // Обработка ошибки
          console.error(error);
        }
      }
    }
  }
  </script>