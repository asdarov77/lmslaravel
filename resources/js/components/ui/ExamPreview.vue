<template>
  <v-card class="exam-preview">
    <div class="exam-preview__header">
      <v-icon icon="mdi-eye-outline" size="18" />
      <span>{{ label }}</span>
    </div>

    <div class="exam-preview__content">
      <div v-if="title" class="exam-preview__title">{{ title }}</div>
      <div v-else class="exam-preview__placeholder">{{ titlePlaceholder }}</div>

      <div v-if="description" class="exam-preview__description">{{ description }}</div>

      <div v-if="categories?.length" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ categoriesLabel || t('exams.category') }}:</span>
        <span class="exam-preview__section-value">{{ categories.join(', ') }}</span>
      </div>

      <div v-if="modules?.length" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.module') }}:</span>
        <span class="exam-preview__section-value">{{ modules.join(', ') }}</span>
      </div>

      <div v-if="totalQuestions !== undefined" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.totalQuestions') }}:</span>
        <span class="exam-preview__section-value">{{ totalQuestions }} {{ t('exams.wizard.questionsCount') }}</span>
      </div>

      <div v-if="perModuleLimits" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.perModuleLimits') }}:</span>
        <div class="exam-preview__limits">
          <span v-for="(limit, moduleId) in perModuleLimits" :key="moduleId" class="exam-preview__limit">
            {{ getModuleTitle(moduleId) }}: {{ limit || t('exams.wizard.noLimit') }}
          </span>
        </div>
      </div>

      <div v-if="shuffle !== undefined" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.shuffle') }}:</span>
        <span class="exam-preview__section-value">{{ shuffle ? t('common.yes') : t('common.no') }}</span>
      </div>

      <div v-if="passingScore !== undefined && passingScore !== null" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.passingScore') }}:</span>
        <span class="exam-preview__section-value">{{ Math.round(passingScore * 100) }}%</span>
      </div>

      <div v-if="maxAttempts !== undefined && maxAttempts !== null" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.maxAttempts') }}:</span>
        <span class="exam-preview__section-value">{{ maxAttempts }}</span>
      </div>

      <div v-if="opensAt" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.opensAt') }}:</span>
        <span class="exam-preview__section-value">{{ formatDate(opensAt) }}</span>
      </div>

      <div v-if="closesAt" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.closesAt') }}:</span>
        <span class="exam-preview__section-value">{{ formatDate(closesAt) }}</span>
      </div>

      <div v-if="dueAt" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.dueAt') }}:</span>
        <span class="exam-preview__section-value">{{ formatDate(dueAt) }}</span>
      </div>

      <div v-if="group" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.assignGroup') }}:</span>
        <span class="exam-preview__section-value">{{ group }}</span>
      </div>

      <div v-if="user" class="exam-preview__section">
        <span class="exam-preview__section-label">{{ t('exams.wizard.assignUser') }}:</span>
        <span class="exam-preview__section-value">{{ user }}</span>
      </div>

      <div v-if="!title && !categories?.length && !modules?.length && totalQuestions === undefined && passingScore === undefined" class="exam-preview__empty">
        {{ categoriesPlaceholder }}
      </div>
    </div>
  </v-card>
</template>

<script>
import { useI18n } from 'vue-i18n'

export default {
  name: 'ExamPreview',
  props: {
    label: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
    totalQuestions: { type: Number, default: undefined },
    perModuleLimits: { type: Object, default: () => ({}) },
    shuffle: { type: Boolean, default: undefined },
    available: { type: Object, default: () => ({}) },
    passingScore: { type: Number, default: undefined },
    maxAttempts: { type: Number, default: undefined },
    opensAt: { type: String, default: '' },
    closesAt: { type: String, default: '' },
    dueAt: { type: String, default: '' },
    group: { type: String, default: '' },
    user: { type: String, default: '' },
    titlePlaceholder: { type: String, default: '—' },
    categoriesPlaceholder: { type: String, default: '—' },
  },
  setup(props) {
    const { t } = useI18n()

    const getModuleTitle = (moduleId) => {
      // В родительском компоненте модули доступны через computed, здесь просто возвращаем ID
      return moduleId
    }

    const formatDate = (value) => {
      if (!value) return ''
      const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value)
      return m ? `${m[3]}.${m[2]}.${m[1]} ${m[4]}:${m[5]}` : value
    }

    return { t, getModuleTitle, formatDate }
  },
}
</script>

<style scoped>
.exam-preview {
  border: 1px dashed var(--c-border-strong);
  border-radius: var(--radius-lg);
  background: var(--c-surface);
  padding: var(--sp-4);
}

.exam-preview__header {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-sm);
  font-weight: 600;
  color: var(--c-text-secondary);
  margin-bottom: var(--sp-4);
}

.exam-preview__title {
  font-size: var(--fs-xl);
  font-weight: 600;
  margin-bottom: var(--sp-2);
}

.exam-preview__description {
  color: var(--c-text-secondary);
  font-size: var(--fs-sm);
  margin-bottom: var(--sp-4);
}

.exam-preview__section {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-1) var(--sp-3);
  margin-bottom: var(--sp-3);
  font-size: var(--fs-sm);
}

.exam-preview__section-label {
  color: var(--c-text-muted);
  font-weight: 500;
}

.exam-preview__section-value {
  font-weight: 500;
  color: var(--c-text);
}

.exam-preview__limits {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
}

.exam-preview__limit {
  background: var(--c-primary-soft);
  color: var(--c-primary);
  padding: 2px var(--sp-2);
  border-radius: var(--radius-sm);
  font-size: var(--fs-xs);
}

.exam-preview__placeholder {
  color: var(--c-text-muted);
  font-style: italic;
  margin-bottom: var(--sp-4);
}

.exam-preview__empty {
  color: var(--c-text-muted);
  font-style: italic;
  text-align: center;
  padding: var(--sp-8) var(--sp-4);
}
</style>