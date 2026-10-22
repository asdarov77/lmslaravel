<template>
  <div class="u-page">
    <PageHeader :title="$t('settings.grades.title')" />

    <v-alert v-if="alert" :type="alert.type" density="compact" class="mb-4">{{ alert.text }}</v-alert>

    <table>
      <caption class="u-sr-only">{{ $t('settings.grades.title') }}</caption>
      <thead>
        <tr>
          <th>{{ $t('gradeSettings.grade') }}</th>
          <th>{{ $t('gradeSettings.boundary') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(grade, index) in gradeBoundaries" :key="index">
          <td>{{ grade.grade }}</td>
          <td>
            <FormField
              :label="$t('gradeSettings.boundary') + ' — ' + $t('gradeSettings.grade') + ' ' + grade.grade"
              :name="`boundary_${index}`"
              :error="boundaryErrors[index]"
            >
              <MaskedField
                v-model="grade.boundary"
                preset="integer"
                :min="minBoundary(index)"
                :max="maxBoundary(index)"
                :aria-label="$t('gradeSettings.boundary') + ' — ' + $t('gradeSettings.grade') + ' ' + grade.grade"
                @update:model-value="updateBoundary(index, $event)"
              />
            </FormField>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import PageHeader from '../../components/ui/PageHeader.vue'
import FormField from '../../components/ui/FormField.vue'
import MaskedField from '../../components/ui/fields/MaskedField.vue'
import $api from '../../api/httpClient'
import { unwrapArray } from '../../api/envelope'

const { t } = useI18n()

const props = defineProps({})

const alert = ref(null)
const gradeBoundaries = ref([])
const boundaryErrors = ref({})
const saving = ref(false)

const apiUrl = import.meta.env.VITE_APP_URL

const minBoundary = (index) => {
  if (index === 0) return 0
  return gradeBoundaries.value[index - 1]?.boundary ?? 0
}

const maxBoundary = (index) => {
  if (index === gradeBoundaries.value.length - 1) return 100
  return gradeBoundaries.value[index + 1]?.boundary ?? 100
}

const updateBoundary = async (index, value) => {
  const boundaries = [...gradeBoundaries.value]
  boundaries[index].boundary = value
  gradeBoundaries.value = boundaries

  // Validate against adjacent boundaries
  const prevBoundary = boundaries[index - 1]?.boundary ?? 0
  const nextBoundary = boundaries[index + 1]?.boundary ?? 100
  boundaryErrors.value = {}

  if (value < prevBoundary) {
    boundaryErrors.value[index] = t('gradeSettings.boundaryCannotBeLess', { prev: prevBoundary })
    return
  }
  if (value > nextBoundary) {
    boundaryErrors.value[index] = t('gradeSettings.boundaryCannotBeGreater', { next: nextBoundary })
    return
  }

  saving.value = true
  try {
    await $api.post('/api/grade-boundary', { index, value })
  } catch (error) {
    boundaryErrors.value[index] = error?.response?.data?.error?.message || t('gradeSettings.saveFailed')
  } finally {
    saving.value = false
  }
}

const load = async () => {
  try {
    const response = await $api.get(apiUrl + '/api/grade-boundary')
    gradeBoundaries.value = unwrapArray(response) || []
  } catch (error) {
    alert.value = { type: 'error', text: t('gradeSettings.loadFailed') }
  }
}

// Watch for external changes to boundaries (e.g., from server response)
watch(gradeBoundaries, (newBoundaries) => {
  // Clear errors for indices that no longer exist
  Object.keys(boundaryErrors.value).forEach((key) => {
    if (parseInt(key) >= newBoundaries.length) {
      delete boundaryErrors.value[key]
    }
  })
}, { deep: true })

load()
</script>