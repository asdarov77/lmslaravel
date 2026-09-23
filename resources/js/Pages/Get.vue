<template>


     {{userData.name}}
     {{userData.id}}

</template>

<script>
   import $api from '../services/http-common'

export default {
  data() {
    return {
      userData: "",
      authToken: ""
    }
  },
  async beforeMount() {
    let response = await $api.get("/getuser").then((response) => {
              console.log(response);
              this.userData = response.data;
             });
    //  this.userData = response.data;
    //  console.log(response);
     //let res = await $api.get("/getuser");

      //localStorage.setItem('token', res.data.data.auth_token);        
  },
};
</script>


