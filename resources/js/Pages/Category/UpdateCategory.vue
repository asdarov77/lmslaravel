<template>
  <div class="u-page">
    <FormCard
      :heading-level="1"
      :title="$t('categories.edit.title')"
      :subtitle="category ? $t('categories.edit.subtitle', { name: category.title }) : ''"
      :busy="form.submitting.value"
      :show-cancel="true"
      :submit-text="$t('common.save')"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <template v-if="category">
        <FormField
          :label="$t('categories.create.name')"
          name="title"
          :error="form.errors.title"
          :required="form.isRequired('title')"
        >
          <v-text-field
            v-bind="form.bind('title')"
            autofocus
          />
        </FormField>

        <FormField
          :label="$t('categories.create.description')"
          name="description"
          :error="form.errors.description"
        >
          <v-textarea
            v-bind="form.bind('description')"
            rows="3"
            auto-grow
          />
        </FormField>
      </template>

      <div v-else class="d-flex justify-center py-6">
        <v-progress-circular indeterminate :aria-label="$t('category.labelZagruzka')"></v-progress-circular>
      </div>

    </FormCard>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import { unwrapResponse } from '@/api/envelope'
import FormCard from '@/components/ui/FormCard.vue'
import FormField from '@/components/ui/FormField.vue'
import useForm from '@/composables/useForm'
import { required, maxLength } from '@/composables/validation/rules'
import { toast } from '@/composables/useToast'

const { t } = useI18n()
const router = useRouter()
const store = useStore()

const props = defineProps({
  idEdit: { type: Number, required: true },
})

const form = useForm({
  schema: {
    title: { initial: '', rules: [required(t('categories.create.errors.nameRequired')), maxLength(255, t('categories.create.errors.nameTooLong'))] },
    description: { initial: '', rules: [maxLength(255, t('categories.create.errors.descriptionTooLong'))] },
  },

  draftKey: `category.edit.${props.idEdit}`,

  onSubmit: async ({ values }) => {
    try {
      await store.dispatch('Course/updateCategory', {
        id: props.idEdit,
        data: {
          title: values.title.trim(),
          description: values.description.trim(),
        },
      })

      await store.dispatch('Course/fetchCategories')
      toast.success(t('categories.edit.done'))
      router.push('/categories')
    } catch (error) {
      console.error('Update category failed:', error)
    }
  },
})

const cancelBtnHead = () => {
    router.back()
  }

  // Expose submitForm for backward compatibility with tests
  const submitForm = () => form.submit()

  const isEdit = computed(() => !!props.idEdit)

  onMounted(async () => {
  await store.dispatch('Course/fetchCategory', props.idEdit).catch(() => {})
})
</script>
