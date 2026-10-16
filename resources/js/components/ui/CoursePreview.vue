<template>
  <!--
    Живое превью карточки курса — «как увидит студент».

    Показывает ровно то, что заполнено: пустые заглушки вместо выдуманного
    контента, потому что превью для того и нужно, чтобы заметить, что
    категория не выбрана или описание пустое, ДО публикации.
  -->
  <aside class="u-preview" :aria-label="t('coursePreview.label')">
    <p class="u-preview__label">{{ previewLabel }}</p>

    <div class="u-preview__cover" aria-hidden="true">
      <v-icon icon="mdi-image-outline" size="28" />
    </div>

    <!--
      h3, а не h1: компонент вставляется в страницу, у которой заголовок
      уровня 1 уже есть (PageHeader). Прежний h3 стоял сразу после h1 —
      уровень 2 пропускался, и оглавление страницы по заголовкам
      получалось рваным (WCAG 2.2 SC 1.3.1).
    -->
    <h2 class="u-preview__title">{{ title || previewTitlePlaceholder }}</h2>

    <p class="u-preview__sub">{{ shortDescription || previewSubPlaceholder }}</p>

    <div v-if="categories.length" class="u-preview__chips">
      <v-chip v-for="name in categories" :key="name" size="x-small" variant="tonal" color="primary">
        {{ name }}
      </v-chip>
    </div>
    <p v-else class="u-preview__missing">{{ previewCategoriesPlaceholder }}</p>

    <p v-if="longDescription" class="u-preview__text">{{ longDescription }}</p>

    <dl class="u-preview__meta">
      <div v-if="path">
        <dt>{{ previewPathLabel }}</dt>
        <dd>{{ path }}</dd>
      </div>
      <div v-if="duration">
        <dt>{{ previewDurationLabel }}</dt>
        <dd>{{ duration }} {{ durationUnit }}</dd>
      </div>
    </dl>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Превью карточки курса для конструктора.
 *
 * Отдельный компонент, а не блок в мастере: превью нужно и на странице
 * списка («как это увидит студент»), и в витрине каталога.
 */
const { t } = useI18n()

const props = defineProps({
  label: { type: String, default: '' },
  title: { type: String, default: '' },
  shortDescription: { type: String, default: '' },
  longDescription: { type: String, default: '' },
  categories: { type: Array, default: () => [] },
  path: { type: String, default: '' },
  duration: { type: [String, Number], default: '' },
  pathLabel: { type: String, default: '' },
  durationLabel: { type: String, default: '' },
  durationUnit: { type: String, default: 'ч' },
  titlePlaceholder: { type: String, default: '' },
  subPlaceholder: { type: String, default: '' },
  categoriesPlaceholder: { type: String, default: '' },
})

// Компьютед для дефолтов через i18n
const previewLabel = computed(() => props.label || t('coursePreview.label'))
const previewTitlePlaceholder = computed(() => props.titlePlaceholder || t('coursePreview.titlePlaceholder'))
const previewSubPlaceholder = computed(() => props.subPlaceholder || t('coursePreview.subPlaceholder'))
const previewCategoriesPlaceholder = computed(() => props.categoriesPlaceholder || t('coursePreview.categoriesPlaceholder'))
const previewPathLabel = computed(() => props.pathLabel || t('coursePreview.pathLabel'))
const previewDurationLabel = computed(() => props.durationLabel || t('coursePreview.durationLabel'))
</script>

<style scoped>
.u-preview {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  padding: var(--sp-4);
  background: var(--c-surface-2);
  border: 1px dashed var(--c-border-strong);
  border-radius: var(--radius-lg);
}

.u-preview__label {
  margin: 0;
  font-size: var(--fs-2xs);
  text-transform: uppercase;
  letter-spacing: var(--tracking-wider);
  color: var(--c-text-muted);
}

.u-preview__cover {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 16 / 9;
  border-radius: var(--radius-md);
  background: var(--c-surface-3);
  color: var(--c-text-muted);
  overflow: hidden;
}


.u-preview__title {
  margin: 0;
  font-size: var(--fs-xl);
  line-height: var(--lh-tight);
  color: var(--c-text);
}

.u-preview__sub {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
}

.u-preview__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1);
}

.u-preview__missing {
  margin: 0;
  font-size: var(--fs-xs);
  color: var(--c-warning);
}

.u-preview__text {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--c-text-secondary);
  /* Многострочное описание не должно растягивать панель на пол-экрана. */
  display: -webkit-box;
  -webkit-line-clamp: 6;
  line-clamp: 6;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.u-preview__meta {
  margin: 0;
  display: grid;
  gap: var(--sp-1);
  font-size: var(--fs-xs);
}

.u-preview__meta > div {
  display: flex;
  justify-content: space-between;
  gap: var(--sp-2);
}

.u-preview__meta dt {
  color: var(--c-text-muted);
}

.u-preview__meta dd {
  margin: 0;
  color: var(--c-text);
  overflow-wrap: anywhere;
}
</style>
