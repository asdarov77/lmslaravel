<template>
  <div class="u-page">
    <!--
      Раньше страница была одной строкой заголовка внутри v-list-item:
      ни <h1> (то есть ни ориентира для скринридера), ни объяснения,
      ни кнопки «куда мне теперь». Человек оказывался в тупике.
      Теперь это обычная страница приложения — с заголовком, текстом
      и навигацией.
    -->
    <PageHeader :title="$t('errors.notFound')" :subtitle="$t('errors.notFoundText')" />

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
  name: 'NotFound',
  components: { PageHeader },
}
</script>