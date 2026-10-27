<template>
  <div class="u-page">
    <FormCard
      :heading-level="1"
      :title="$t('categories.create.title')"
      :subtitle="$t('categories.create.subtitle')"
      :busy="form.submitting.value"
      :submit-text="$t('common.create')"
      @submit="submitForm"
      @cancel="cancelBtnHead"
    >
      <FormField
        :label="$t('categories.create.name')"
        name="title"
        :error="form.errors.title"
        :required="form.isRequired('title')"
      >
        <v-text-field
          v-bind="form.bind('title')"
          :placeholder="$t('categories.create.namePlaceholder')"
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
          :placeholder="$t('categories.create.descriptionPlaceholder')"
          rows="3"
          auto-grow
        />
      </FormField>

    </FormCard>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import FormCard from '@/components/ui/FormCard.vue'
import FormField from '@/components/ui/FormField.vue'
import { unwrapResponse } from '@/api/envelope'
import useForm from '@/composables/useForm'
import { required, maxLength } from '@/composables/validation/rules'
import { toast } from '@/composables/useToast'

const { t } = useI18n()
const router = useRouter()
const store = useStore()

const form = useForm({
  schema: {
    title: { initial: '', rules: [required(t('categories.create.errors.nameRequired')), maxLength(255, t('categories.create.errors.nameTooLong'))] },
    description: { initial: '', rules: [maxLength(255, t('categories.create.errors.descriptionTooLong'))] },
  },

  onSubmit: async ({ values }) => {
    try {
      const response = await store.dispatch('Course/createCategory', {
        title: values.title.trim(),
        description: values.description.trim(),
      })

      toast.success(t('categories.create.done'))
      router.push('/categories')
    } catch (error) {
      // Error handled by useForm interceptors and toast
      console.error('Create category failed:', error)
    }
  },

  cancelBtnHead() {
    router.back()
  },
})
</script>
