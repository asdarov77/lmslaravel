<template>
  <!-- <v-dialog v-model="dialog" persistent true> -->
    <v-dialog :model-value="dialog" persistent true>
    <v-card width="500">
      <v-card-title class="text-h5 grey lighten-2">
        {{ title }}
      </v-card-title>
      <v-card-text>{{ text }}</v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn color="red darken-1" text @click="cancel"> Отменить </v-btn>
        <v-btn color="green darken-1" text @click="agree"> Удалить </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
  
  <script>
export default {
  data() {
    return {
      dialog: false,
    };
  },
  props: {
    dialog: Boolean,
    text: Text,
    title: Text,
  },

  methods: {
    cancel() {
      this.$emit("dialogClose");
      //console.log('disagree');
    },
    agree() {
      //console.log('agree');
      this.$emit("dialogClose");
      this.$emit("confirm");
    },
  },
};
</script>
  

<style scoped>
  .v-overlay.v-overlay--active{
            opacity: 0.25;
          }
</style>

