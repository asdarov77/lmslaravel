<template>
  <div class="u-page">
    <PageHeader :title="$t('errors.serverError')" :subtitle="$t('errors.serverErrorText')" />

    <v-card class="u-card mt-4">
      <v-card-text class="d-flex flex-wrap ga-3 align-center">
        <v-btn color="primary" variant="flat" :to="{ name: 'dashboard' }" data-test="error-home">
          {{ $t("errors.goHome") }}
        </v-btn>
        <v-btn variant="text" @click="$router.go(-1)" data-test="error-back">
          {{ $t("errors.goBack") }}
        </v-btn>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'

export default {
  name: 'InternalServerError',
  components: { PageHeader },
}
</script>