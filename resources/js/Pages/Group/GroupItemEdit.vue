<template>
  <div class="u-page">
    <FormCard
      :heading-level="1"
      :title="t('groups.edit.title')"
      :subtitle="subtitle"
      :busy="form.submitting.value"
      :loading="loading"
      @submit="submitForm"
      @cancel="cancel"
    >
      <FormField
        :label="t('groups.create.name')"
        name="groupname"
        :error="form.errors.groupname"
        :required="form.isRequired('groupname')"
      >
        <v-text-field
          v-bind="form.bind('groupname')"
          :placeholder="t('groups.create.namePlaceholder')"
          autofocus
        />
      </FormField>

      <FormField
        :label="t('groups.create.description')"
        name="groupdescription"
        :error="form.errors.groupdescription"
      >
        <AutosizeTextarea
            :label-id="`field-groupdescription-label`"
          v-model="form.values.groupdescription"
          :maxlength="255"
          :rows="3"
          :placeholder="t('groups.create.descriptionPlaceholder')"
          @blur="form.validateField('groupdescription')"
        />
      </FormField>

      <!--
        Второе действие формы, а не кнопка цвета «green» внутри карточки:
        раньше зелёная кнопка рядом с синей кнопкой сохранения читалась
        как отдельное главное действие.
      -->
      <v-btn
        variant="tonal"
        color="primary"
        prepend-icon="mdi-school-outline"
        :disabled="form.submitting.value || !groupId"
        :to="{ name: 'group.learning', params: { idEdit: groupId } }"
      >
        {{ t('groups.edit.enroll') }}
      </v-btn>
    </FormCard>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
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
 * Редактирование группы.
 *
 * Переведена на useForm (Фаза 2) вместе с CreateGroup. Ключевое отличие
 * правила уникальности: при правке сама группа не должна считаться
 * дублем — без `ignore` правка названия всегда падала бы с «уже занято».
 */
const props = defineProps({
  idEdit: { type: [Number, String], required: true },
})

const { t } = useI18n()
const store = useStore()
const router = useRouter()

const loading = ref(false)

const groupId = computed(() => store.state.User.group?.id ?? Number(props.idEdit))

const subtitle = computed(() => {
  const name = store.state.User.group?.groupname

  return name ? t('groups.edit.subtitle', { name }) : ''
})

const form = useForm({
  schema: {
    groupname: {
      initial: '',
      rules: [
        required(t('groups.create.errors.nameRequired')),
        maxLength(255, t('groups.create.errors.nameTooLong')),
        unique(() => asArray(store.state.User.allGroups), {
          by: 'groupname',
          // Без этого собственная группа считалась бы дубликатом.
          ignore: () => Number(props.idEdit),
          message: t('groups.create.errors.nameTaken'),
        }),
      ],
    },
    groupdescription: {
      initial: '',
      rules: [maxLength(255, t('groups.create.errors.descriptionTooLong'))],
    },
  },

  draftKey: `groups.edit.${props.idEdit}`,

  onSubmit: async ({ values }) => {
    await store.dispatch('User/updateGroup', {
      id: props.idEdit,
      data: {
        groupname: values.groupname.trim(),
        groupdescription: values.groupdescription.trim(),
      },
    })

    toast.success(t('groups.edit.done'))
    await router.push('/groups/list')
  },
})

const { confirmLeave } = useLeaveGuard(() => form.dirty.value, { message: t('common.leaveConfirm') })

onMounted(async () => {
  loading.value = true
  await store.dispatch('User/fetchGroup', props.idEdit).catch(() => {})

  // Заполняем форму только что загруженными данными. До этого пустые
  // поля показывались как «форма без данных», а правило уникальности
  // проверяло пустое имя по всему списку групп.
  const group = store.state.User.group
  if (group) {
    form.values.groupname = group.groupname ?? ''
    form.values.groupdescription = group.groupdescription ?? ''
  }

  loading.value = false
  // Данные пришли — форма не «грязная», уходить можно без вопроса.
  form.reset({ groupname: group?.groupname ?? '', groupdescription: group?.groupdescription ?? '' })
})

const submitForm = () => {
  form.submit()
}

const cancel = async () => {
  if (await confirmLeave()) router.back()
}
</script>