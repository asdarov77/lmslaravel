<template>
  <v-card-actions class="d-flex justify-content-between">
    <v-spacer></v-spacer>
    <v-btn @click="cancelBtn" color="error">{{ $t("app.buttons.cancel") }}</v-btn>
    <v-btn @click="submitForm" color="primary">{{ $t("app.buttons.save") }}</v-btn>
  </v-card-actions>
</template>

<script setup>
import { useI18n } from "vue-i18n";
const { t } = useI18n({ useScope: "global" });
</script>
<script>
export default {
  name: "ButtonGroup",
  methods: {
    submitForm() {
      this.$emit('submitForm')
    },
    cancelBtn() {
      this.$emit('cancelBtn')
    }
  }
}
</script>

<style scoped>

</style>