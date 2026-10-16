<template>
  <div class="u-page">
    <PageHeader :title="$t('settings.title')" :subtitle="$t('settings.subtitle')" />

    <TabsForm :tabs="tabs">
      <template #delivery>
        <ContentDeliverySettings />
      </template>

      <template #tutor>
        <TutorSettings />
      </template>

      <template #grades>
        <GradeSettings />
      </template>
    </TabsForm>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/ui/PageHeader.vue'
import TabsForm from '../../components/ui/TabsForm.vue'
import ContentDeliverySettings from './ContentDeliverySettings.vue'
import TutorSettings from './TutorSettings.vue'
import GradeSettings from './GradeSettings.vue'

/**
 * Настройки — одна форма с вкладками.
 *
 * Раньше это были вертикальные вкладки Vuetify внутри пустой v-card, а
 * список категорий задавался массивом с динамическим <component :is>.
 * Теперь вкладки описаны данными и рисуются общим TabsForm дизайн-
 * системы: та же полоса вкладок, те же состояния, тот же компонент на
 * остальных страницах.
 *
 * Вкладки по-прежнему включают мгновенное сохранение: каждый раздел
 * сохраняет себя сам, кнопки «Сохранить» в настройках нет и не должно
 * быть — состояние «галка включена, а на сервере php» вреднее всего.
 */
const { t } = useI18n()

const tabs = computed(() => [
  { key: 'delivery', title: t('settings.delivery.tab'), icon: 'mdi-server-network' },
  { key: 'tutor', title: t('settings.tutor.tab'), icon: 'mdi-school-outline' },
  // Наполнения пока нет: вкладка помечена как незавершённая, а не
  // выдаёт себя за рабочую.
  { key: 'grades', title: t('settings.grades.tab'), icon: 'mdi-chart-line' },
])
</script>
