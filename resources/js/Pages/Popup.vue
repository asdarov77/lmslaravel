<template>
 
  <v-overlay :model-value="alert">
    <v-alert :type="alertType" :model-value="alert" >
      {{ snackbarText }}
    </v-alert>
  </v-overlay>  
</template>

<script>



export default {
  name: 'popup',

 props:['snackbarText','alert','alertType','overlay','alertFalse'],
  data() {
     return {

    }    
  },

  watch: {
    alert(new_val) {
      if (new_val) {
        setTimeout(() => {
          //this.alert = false;          
          this.alertFalse()
        }, 3000);
      }
    },
  },
 
};
</script>

<style scooped>
.v-overlay {
  display: flex;	
  justify-content: center;	
  top: 50%;
}
</style>