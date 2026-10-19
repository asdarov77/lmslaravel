<template>
  <div class="u-page">
    <!--
      Заголовок — через PageHeader: на остальных страницах заголовок
      рисует именно он, и страница ошибки не должна выглядеть как
      чужеродная карточка.
    -->
    <PageHeader :title="$t('errors.forbidden')" :subtitle="$t('errors.forbiddenText')" />

    <v-card class="u-card mt-4">
      <v-card-text class="d-flex flex-wrap ga-3 align-center">
        <v-btn color="primary" variant="flat" :to="{ name: 'dashboard' }" data-test="error-home">
          {{ $t("errors.goHome") }}
        </v-btn>
        <v-btn variant="text" :to="{ name: 'learning.plan' }" data-test="error-plan">
          {{ $t("errors.goPlan") }}
        </v-btn>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'

export default {
  name: 'Forbidden',
  components: { PageHeader },
}
</script>