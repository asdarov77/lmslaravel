<template>
  <div class="u-page">
    <!--
      Заголовок страницы — только через PageHeader: раньше половина
      страниц рисовала h1/h2 вручную, и заголовки выглядели по-разному.
    -->
    <PageHeader :title="$t('errors.forbidden')" :subtitle="$t('errors.forbiddenText')" />
  </div>
</template>

<script>
import PageHeader from '../../components/ui/PageHeader.vue'

export default {
  name: 'Forbidden',
  components: { PageHeader },
}
</script>
