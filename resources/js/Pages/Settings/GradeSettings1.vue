<template>
  <div>
    <v-title>Страница настроек1</v-title>

    <form @submit.prevent="saveSettings">


      <div v-for="setting in settings" :key="setting.name">
        <label>{{ setting.name }}</label>
        <div v-if="setting.type === 'type1'">
          <v-checkbox v-model="setting.value"></v-checkbox>
        </div>
        <div v-else-if="setting.type === 'type2'">
          <v-text-field v-model="setting.value"></v-text-field>
        </div>
      </div>
      <v-btn type="submit">Сохранить</v-btn>

    </form>

  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import $api from "../../api/httpClient";
const apiUrl = import.meta.env.VITE_APP_URL;

export default {
  name: 'SettingsPage',
  setup() {
    const settings = ref([]);

    const fetchSettings = async () => {
      try {
        const response = await $api.get(apiUrl + '/api/settings');
        settings.value = response.data;
      } catch (error) {
        console.error(error);
      }
    };

    const saveSettings = async () => {
      try {
        await $api.post(apiUrl + '/api/settings', settings.value);
        alert('Настройки сохранены!');
      } catch (error) {
        console.error(error);
      }
    };

    onMounted(fetchSettings);

    return {
      settings,
      saveSettings,
    };
  },
};
</script>

