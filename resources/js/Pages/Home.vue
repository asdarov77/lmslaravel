<template>
  <component :is="viewComponent"></component> 
  <p>home</p>{{ user.role }}
</template>

<script>
import UserPage from "./User/UserPage.vue";
import MyAccount from "./MyAccount.vue";
import {mapGetters, mapState} from "vuex";

const roleComponentMapping = {
  'Обучаемый': UserPage,
  'Администратор': MyAccount,
  'Инструктор': MyAccount
}

export default {
    data() {
      return {
        // viewComponent: roleComponentMapping[this.$store.Auth.state.user.role]
        viewComponent: roleComponentMapping[this.us],
        us: "",
      }
    },
    mounted() {
      const us = this.user.role
    },

    computed: {
    ...mapGetters("Auth", ["loggedIn","user"]),    
    ...mapState("Ui", ["menudrawler", "language"]),
    ...mapState("Auth", ["user"])
  },

}
</script>