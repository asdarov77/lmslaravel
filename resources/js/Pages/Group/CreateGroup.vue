<template>
  <div class="u-page">
    <FormCard
      :title="t('groups.create.title')"
      :subtitle="t('groups.create.subtitle')"
      :busy="form.submitting.value"
      :submit-text="t('common.create')"
      @submit="submitForm"
      @cancel="cancel"
    >
      <!--
        Общая ошибка сервера (не 422): показываем здесь, а не над каждым
        полем. Ошибки конкретных полей useForm раскладывает по form.errors
        и подсвечивает поле само.
      -->
      <v-alert
        v-if="form.serverError.value"
        type="error"
        variant="tonal"
        class="mb-4"
        :text="form.serverError.value"
      />

      <FormField
        :label="t('groups.create.name')"
        :error="form.errors.groupname"
        :required="form.isRequired('groupname')"
        name="groupname"
      >
        <v-text-field
          v-bind="form.bind('groupname')"
          :placeholder="t('groups.create.namePlaceholder')"
          autofocus
        />
      </FormField>

      <FormField
        :label="t('groups.create.description')"
        :error="form.errors.groupdescription"
        name="groupdescription"
      >
        <AutosizeTextarea
          v-model="form.values.groupdescription"
          :maxlength="255"
          :rows="3"
          :placeholder="t('groups.create.descriptionPlaceholder')"
          @blur="form.validateField('groupdescription')"
        />
      </FormField>

      <p v-if="form.draftSavedAt.value" class="u-draft-hint">
        <v-icon icon="mdi-content-save-check-outline" size="16" aria-hidden="true" />
        {{ t('common.draftSaved') }}
      </p>
    </FormCard>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import FormCard from '../../components/ui/FormCard.vue'
import FormField from '../../components/ui/FormField.vue'
import AutosizeTextarea from '../../components/ui/fields/AutosizeTextarea.vue'
import useForm from '../../composables/useForm'
import useLeaveGuard from '../../composables/useLeaveGuard'
import { required, maxLength, unique } from '../../composables/validation/rules'
import { asArray } from '../../api/envelope'
import { toast } from '../../composables/useToast'

/**
 * Создание группы.
 *
 * Переведена на useForm (Фаза 2): вместо ручного `fieldErrors` —
 * объект-схема; вместо `if (!this.errors.length)` (где массив ошибок
 * нигде не наполнялся) — проверка правил до запроса; серверный 422
 * раскладывается по полям, а не висит одним блоком; уход с формы
 * требует подтверждения; длинное описание автосохраняется как черновик.
 */
const { t } = useI18n()
const store = useStore()
const router = useRouter()

const form = useForm({
  schema: {
    groupname: {
      initial: '',
      rules: [
        required(t('groups.create.errors.nameRequired')),
        maxLength(255, t('groups.create.errors.nameTooLong')),
        // Уникальность проверяем по уже загруженному списку групп.
        unique(() => asArray(store.state.User.allGroups), {
          by: 'groupname',
          message: t('groups.create.errors.nameTaken'),
        }),
      ],
    },
    groupdescription: {
      initial: '',
      rules: [maxLength(255, t('groups.create.errors.descriptionTooLong'))],
    },
  },

  draftKey: 'groups.create',

  onSubmit: async ({ values }) => {
    await store.dispatch('User/createGroup', {
      groupname: values.groupname.trim(),
      groupdescription: values.groupdescription.trim(),
    })

    toast.success(t('groups.create.done'))
    await router.push('/groups/list')
  },
})

useLeaveGuard(() => form.dirty.value)

const submitForm = () => {
  form.submit()
}

const cancel = () => {
  router.back()
}
</script>

<style scoped>
.u-draft-hint {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin: var(--sp-2) 0 0;
  font-size: var(--fs-xs);
  color: var(--c-text-muted);
}
</style>
