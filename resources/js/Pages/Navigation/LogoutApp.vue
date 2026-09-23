<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>

<template>
  <v-list-item
    prepend-icon="mdi-location-exit"
    title='Выход'
    v-on:click="logout"
    class="logout-btn"
  ></v-list-item>
</template>

<script>

export default {
  components: {},
  data() {
    return {};
  },
  methods: {
    logout() {
      //console.log("logout");
        this.$store.dispatch('Auth/logout')
        .then(() => { 
        this.$store.dispatch('Ui/logout');                   
        //this.$store.dispatch('reset');
        this.$router.push('/login');
      });      
    },
  },
};
</script>
<!-- <style>
.logout-btn {
  background-color: white;
  height: 80px;
}
</style> -->
